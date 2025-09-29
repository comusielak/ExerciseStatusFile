<?php
declare(strict_types=1);

/**
 * Feedback Upload Handler
 * 
 * Verarbeitet hochgeladene Feedback-ZIPs und wendet Status-Updates an
 * Unterstützt Individual- und Team-Assignments
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExFeedbackUploadHandler
{
    private ilLogger $logger;
    private array $temp_directories = [];
    private array $processing_stats = [];
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        
        register_shutdown_function([$this, 'cleanupAllTempDirectories']);
    }
    
    /**
     * Feedback Upload Processing
     */
    public function handleFeedbackUpload(array $parameters): void
    {
        $assignment_id = $parameters['assignment_id'] ?? 0;
        $tutor_id = $parameters['tutor_id'] ?? 0;
        
        if (!$assignment_id) {
            $this->logger->warning("Upload handler: Missing assignment_id");
            return;
        }
        
        try {
            $assignment = new \ilExAssignment($assignment_id);
            $zip_content = $this->extractZipContent($parameters);
            
            if (!$zip_content || !$this->isValidZipContent($zip_content)) {
                $this->logger->warning("Upload handler: Invalid ZIP content");
                return;
            }
            
            if ($assignment->getAssignmentType()->usesTeams()) {
                $this->processTeamUpload($zip_content, $assignment_id, $tutor_id);
            } else {
                $this->processIndividualUpload($zip_content, $assignment_id, $tutor_id);
            }
            
            $this->setProcessingSuccess($assignment_id, $tutor_id);
            
        } catch (Exception $e) {
            $this->logger->error("Upload handler error: " . $e->getMessage());
        }
    }
    
    /**
     * Team Assignment Upload Processing
     */
    private function processTeamUpload(string $zip_content, int $assignment_id, int $tutor_id): void
    {
        $temp_zip = $this->createTempZipFile($zip_content, 'team_feedback');
        if (!$temp_zip) return;
        
        try {
            $this->validateZipForAssignment($temp_zip, $assignment_id);
            
            $extracted_files = $this->extractZipContents($temp_zip, 'team_extract');
            $status_files = $this->findStatusFiles($extracted_files);
            
            if (!empty($status_files)) {
                $this->processStatusFiles($status_files, $assignment_id, true);
            }
            
            $this->processTeamFeedbackFiles($extracted_files, $assignment_id);
            
        } finally {
            $this->cleanupTempFile($temp_zip);
        }
    }
    
    /**
     * Individual Assignment Upload Processing
     */
    private function processIndividualUpload(string $zip_content, int $assignment_id, int $tutor_id): void
    {
        $temp_zip = $this->createTempZipFile($zip_content, 'individual_feedback');
        if (!$temp_zip) return;
        
        try {
            $this->validateZipForAssignment($temp_zip, $assignment_id);
            
            $extracted_files = $this->extractZipContents($temp_zip, 'individual_extract');
            $status_files = $this->findStatusFiles($extracted_files);
            
            if (!empty($status_files)) {
                $this->processStatusFiles($status_files, $assignment_id, false);
            }
            
            $this->processIndividualFeedbackFiles($extracted_files, $assignment_id);
            
        } finally {
            $this->cleanupTempFile($temp_zip);
        }
    }
    
    /**
     * Umfassende ZIP-Validierung für Assignment
     */
    private function validateZipForAssignment(string $zip_path, int $assignment_id): void
    {
        $zip = new \ZipArchive();
        $zip_result = $zip->open($zip_path);
        
        if ($zip_result !== true) {
            throw new Exception("Die hochgeladene Datei ist kein gültiges ZIP-Archiv (Code: $zip_result).");
        }
        
        if ($zip->numFiles === 0) {
            $zip->close();
            throw new Exception("Das ZIP-Archiv ist leer.");
        }
        
        // Nur bei Problemen loggen
        $assignment = new \ilExAssignment($assignment_id);
        $is_team_assignment = $assignment->getAssignmentType()->usesTeams();
        
        $file_list = [];
        $status_files_found = [];
        $has_team_structure = false;
        $has_user_structure = false;
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $file_list[] = $filename;
            
            $basename = basename($filename);
            $status_file_patterns = [
                'status.xlsx', 'status.csv', 'status.xls',
                'batch_status.xlsx', 'batch_status.csv'
            ];
            
            foreach ($status_file_patterns as $pattern) {
                if (strcasecmp($basename, $pattern) === 0) {
                    $status_files_found[] = $filename;
                    break;
                }
            }
            
            if (preg_match('/Team_\d+\//', $filename)) {
                $has_team_structure = true;
            }
            
            if (preg_match('/[^\/]+_[^\/]+_[^\/]+_\d+\//', $filename)) {
                $has_user_structure = true;
            }
        }
        
        $zip->close();
        
        // Validierungen
        if (empty($status_files_found)) {
            $available_files = array_map('basename', $file_list);
            throw new Exception(
                "Keine Status-Dateien im ZIP gefunden.\n\n" .
                "Erwartet: status.xlsx, status.csv\n" .
                "Gefunden: " . implode(', ', array_slice($available_files, 0, 10))
            );
        }
        
        if ($is_team_assignment && !$has_team_structure) {
            throw new Exception("Team-Assignment benötigt Team-Ordner (Team_1/, Team_2/, etc.)");
        }
        
        if (!$is_team_assignment && $has_team_structure) {
            throw new Exception("Individual-Assignment darf keine Team-Ordner enthalten.");
        }
        
        if (!$has_user_structure) {
            throw new Exception("Keine User-Ordner (Lastname_Firstname_Login_ID/) im ZIP gefunden.");
        }
    }
    
    /**
     * ZIP-Content aus Upload-Parameters extrahieren
     */
    private function extractZipContent(array $parameters): ?string
    {
        if (isset($parameters['zip_content']) && is_string($parameters['zip_content'])) {
            return $parameters['zip_content'];
        }
        
        if (isset($parameters['upload_result'])) {
            return $this->getZipContentFromUploadResult($parameters['upload_result']);
        }
        
        if (isset($parameters['zip_path']) && file_exists($parameters['zip_path'])) {
            return file_get_contents($parameters['zip_path']);
        }
        
        return null;
    }
    
    /**
     * ZIP-Content aus Upload-Result extrahieren
     */
    private function getZipContentFromUploadResult($upload_result): ?string
    {
        if (method_exists($upload_result, 'getPath') && $upload_result->getPath()) {
            $temp_path = $upload_result->getPath();
            if (file_exists($temp_path)) {
                return file_get_contents($temp_path);
            }
        }
        
        return null;
    }
    
    /**
     * Prüft ob Content ein gültiges ZIP ist
     */
    private function isValidZipContent(string $content): bool
    {
        if (empty($content)) {
            throw new Exception("Die hochgeladene Datei ist leer.");
        }
        
        if (strlen($content) < 100) {
            throw new Exception("Die hochgeladene Datei ist zu klein.");
        }
        
        if (substr($content, 0, 2) !== 'PK') {
            throw new Exception("Die hochgeladene Datei ist kein gültiges ZIP-Archiv.");
        }
        
        return true;
    }
    
    /**
     * Erstellt temporäre ZIP-Datei
     */
    private function createTempZipFile(string $zip_content, string $prefix): ?string
    {
        $temp_zip = sys_get_temp_dir() . '/plugin_' . $prefix . '_' . uniqid() . '.zip';
        
        if (file_put_contents($temp_zip, $zip_content) === false) {
            $this->logger->error("Could not create temp ZIP file");
            return null;
        }
        
        if (!file_exists($temp_zip) || filesize($temp_zip) < 100) {
            $this->logger->error("Invalid temp ZIP file created");
            return null;
        }
        
        return $temp_zip;
    }
    
    /**
     * Extrahiert ZIP-Inhalte
     */
    private function extractZipContents(string $zip_path, string $extract_prefix): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            $this->logger->error("Could not open ZIP file");
            return [];
        }
        
        $extract_dir = $this->createTempDirectory($extract_prefix);
        $extracted_files = [];
        
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (empty($filename) || substr($filename, -1) === '/') continue;
                
                $zip->extractTo($extract_dir, $filename);
                $extracted_path = $extract_dir . '/' . $filename;
                
                if (file_exists($extracted_path)) {
                    $extracted_files[] = [
                        'original_name' => $filename,
                        'extracted_path' => $extracted_path,
                        'size' => filesize($extracted_path)
                    ];
                }
            }
            
        } finally {
            $zip->close();
        }
        
        return $extracted_files;
    }
    
    /**
     * Findet Status-Files in extrahierten Dateien
     */
    private function findStatusFiles(array $extracted_files): array
    {
        $status_files = [];
        
        foreach ($extracted_files as $file) {
            $basename = basename($file['original_name']);
            
            if (in_array($basename, ['status.xlsx', 'status.csv', 'status.xls']) ||
                in_array($basename, ['batch_status.xlsx', 'batch_status.csv'])) {
                $status_files[] = $file['extracted_path'];
            }
        }
        
        return $status_files;
    }
    
    /**
     * Verarbeitet Status-Files und wendet Updates an
     */
    private function processStatusFiles(array $status_files, int $assignment_id, bool $is_team): void
    {
        if (empty($status_files)) {
            throw new Exception("Keine gültigen Status-Dateien gefunden.");
        }
        
        try {
            $this->clearAssignmentCaches($assignment_id);
            
            $assignment = new \ilExAssignment($assignment_id);
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            $status_file->allowPlagiarismUpdate(true);
            
            $updates_applied = false;
            $load_errors = [];
            
            foreach ($status_files as $file_path) {
                if (!file_exists($file_path)) continue;
                
                try {
                    $status_file = new ilPluginExAssignmentStatusFile();
                    $status_file->init($assignment);
                    $status_file->allowPlagiarismUpdate(true);
                    
                    $status_file->loadFromFile($file_path);
                    
                    if ($status_file->isLoadFromFileSuccess()) {
                        if ($status_file->hasUpdates()) {
                            $updates = $status_file->getUpdates();
                            $updates_count = count($updates);
                            
                            $this->clearAssignmentCaches($assignment_id);
                            $status_file->applyStatusUpdates();
                            $this->clearAssignmentCaches($assignment_id);
                            
                            global $DIC;
                            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $status_file->getInfo(), true);
                            
                            $this->processing_stats['status_updates'] = $updates_count;
                            $this->processing_stats['processed_file'] = basename($file_path);
                            $this->processing_stats['timestamp'] = date('Y-m-d H:i:s');
                            $updates_applied = true;
                            
                            // Nur erfolgreiche Updates loggen
                            $this->logger->info("Applied $updates_count status updates from " . basename($file_path));
                            break;
                            
                        } else {
                            $load_errors[] = "Keine Updates in " . basename($file_path) . " gefunden.";
                        }
                    } else {
                        if ($status_file->hasError()) {
                            $load_errors[] = "Fehler beim Laden von " . basename($file_path) . ": " . $status_file->getInfo();
                            #$this->logger->error("Error loading " . basename($file_path) . ": " . $status_file->getInfo()); # not functioning?
                        } else {
                            $load_errors[] = "Datei " . basename($file_path) . " konnte nicht geladen werden.";
                        }
                    }
                    
                } catch (Exception $e) {
                    $load_errors[] = "Fehler beim Verarbeiten von " . basename($file_path) . ": " . $e->getMessage();
                    $this->logger->error("Exception processing " . basename($file_path) . ": " . $e->getMessage());
                }
            }
            
            if (!$updates_applied) {
                $error_msg = "Keine Status-Updates wurden angewendet. ";
                if (!empty($load_errors)) {
                    $error_msg .= "Probleme: " . implode(" | ", $load_errors);
                }
                throw new Exception($error_msg);
            }
            
            $this->clearAssignmentCaches($assignment_id);
            
        } catch (Exception $e) {
            $this->logger->error("Error in status file processing: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Assignment-Caches leeren
     */
    private function clearAssignmentCaches(int $assignment_id): void
    {
        try {
            // Session-Cache leeren
            $session_keys_to_clear = [
                'exc_assignment_' . $assignment_id,
                'exc_members_' . $assignment_id,
                'exc_status_files_processed',
                'exc_status_files_stats',
                'exc_teams_' . $assignment_id
            ];
            
            foreach ($session_keys_to_clear as $key) {
                if (isset($_SESSION[$key])) {
                    unset($_SESSION[$key]);
                }
            }
            
            // Globale Caches
            if (isset($GLOBALS['assignment_cache_' . $assignment_id])) {
                unset($GLOBALS['assignment_cache_' . $assignment_id]);
            }
            
            // Garbage Collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Cache clearing failed: " . $e->getMessage());
        }
    }
    
    /**
     * Verarbeitet Team-spezifische Feedback-Files
     */
    private function processTeamFeedbackFiles(array $extracted_files, int $assignment_id): void
    {
        $team_feedback_files = $this->findTeamFeedbackFiles($extracted_files);
        
        if (empty($team_feedback_files)) {
            return;
        }
        
        foreach ($team_feedback_files as $team_id => $files) {
            $this->processTeamSpecificFeedback($team_id, $files, $assignment_id);
        }
        
        $this->processing_stats['team_feedback_files'] = count($team_feedback_files);
    }
    
    /**
     * Verarbeitet Individual Feedback-Files
     */
    private function processIndividualFeedbackFiles(array $extracted_files, int $assignment_id): void
    {
        $individual_feedback_files = $this->findIndividualFeedbackFiles($extracted_files);
        
        if (empty($individual_feedback_files)) {
            return;
        }
        
        foreach ($individual_feedback_files as $user_id => $files) {
            $this->processUserSpecificFeedback($user_id, $files, $assignment_id);
        }
        
        $this->processing_stats['individual_feedback_files'] = count($individual_feedback_files);
    }
    
    /**
     * Findet Team-Feedback-Files in ZIP-Struktur
     */
    private function findTeamFeedbackFiles(array $extracted_files): array
    {
        $team_files = [];
        
        foreach ($extracted_files as $file) {
            $path = $file['original_name'];
            
            if (preg_match('/Team_(\d+)\/[^\/]+\/(.+)/', $path, $matches)) {
                $team_id = (int)$matches[1];
                $filename = $matches[2];
                
                if (!isset($team_files[$team_id])) {
                    $team_files[$team_id] = [];
                }
                
                $team_files[$team_id][] = [
                    'filename' => $filename,
                    'path' => $file['extracted_path'],
                    'original_path' => $path
                ];
            }
        }
        
        return $team_files;
    }
    
    /**
     * Findet Individual-Feedback-Files
     */
    private function findIndividualFeedbackFiles(array $extracted_files): array
    {
        $individual_files = [];
        
        foreach ($extracted_files as $file) {
            $path = $file['original_name'];
            
            if (preg_match('/[^\/]+_[^\/]+_[^\/]+_(\d+)\/(.+)/', $path, $matches)) {
                $user_id = (int)$matches[1];
                $filename = $matches[2];
                
                if (!isset($individual_files[$user_id])) {
                    $individual_files[$user_id] = [];
                }
                
                $individual_files[$user_id][] = [
                    'filename' => $filename,
                    'path' => $file['extracted_path'],
                    'original_path' => $path
                ];
            }
        }
        
        return $individual_files;
    }
    
    /**
     * Verarbeitet Team-spezifisches Feedback
     */
    private function processTeamSpecificFeedback(int $team_id, array $files, int $assignment_id): void
    {
        // Platzhalter für erweiterte Team-Feedback-Logic
    }
    
    /**
     * Verarbeitet User-spezifisches Feedback
     */
    private function processUserSpecificFeedback(int $user_id, array $files, int $assignment_id): void
    {
        // Platzhalter für erweiterte Individual-Feedback-Logic
    }
    
    /**
     * Setzt Processing-Success-Flag
     */
    private function setProcessingSuccess(int $assignment_id, int $tutor_id): void
    {
        $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] = time();
        $_SESSION['exc_status_files_stats'][$assignment_id][$tutor_id] = $this->processing_stats;
    }
    
    /**
     * Temp-Directory erstellen
     */
    private function createTempDirectory(string $prefix): string
    {
        $temp_dir = sys_get_temp_dir() . '/plugin_' . $prefix . '_' . uniqid();
        mkdir($temp_dir, 0777, true);
        $this->temp_directories[] = $temp_dir;
        
        return $temp_dir;
    }
    
    /**
     * Temp-File aufräumen
     */
    private function cleanupTempFile(string $file_path): void
    {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    /**
     * Alle Temp-Directories aufräumen
     */
    public function cleanupAllTempDirectories(): void
    {
        foreach ($this->temp_directories as $temp_dir) {
            if (is_dir($temp_dir)) {
                $this->cleanupTempDirectory($temp_dir);
            }
        }
        $this->temp_directories = [];
    }
    
    /**
     * Einzelnes Temp-Directory aufräumen
     */
    private function cleanupTempDirectory(string $temp_dir): void
    {
        try {
            $files = glob($temp_dir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    } elseif (is_dir($file)) {
                        $this->cleanupTempDirectory($file);
                    }
                }
            }
            rmdir($temp_dir);
        } catch (Exception $e) {
            // Silent cleanup failure
        }
    }
    
    /**
     * Get Processing Statistics
     */
    public function getProcessingStats(): array
    {
        return $this->processing_stats;
    }
}
?>
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
            // Umfassende ZIP-Validierung
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
            // Umfassende ZIP-Validierung
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
        // 1. Ist es überhaupt ein gültiges ZIP?
        $zip = new \ZipArchive();
        $zip_result = $zip->open($zip_path);
        
        if ($zip_result !== true) {
            throw new Exception("Die hochgeladene Datei ist kein gültiges ZIP-Archiv (Fehlercode: $zip_result).");
        }
        
        // 2. Ist das ZIP leer?
        if ($zip->numFiles === 0) {
            $zip->close();
            throw new Exception("Das ZIP-Archiv ist leer. Bitte laden Sie eine gültige Multi-Feedback ZIP-Datei hoch.");
        }
        
        $this->logger->info("ZIP validation - found " . $zip->numFiles . " files in archive");
        
        // 3. Assignment-Info laden für Vergleich
        $assignment = new \ilExAssignment($assignment_id);
        $assignment_title = $assignment->getTitle();
        $is_team_assignment = $assignment->getAssignmentType()->usesTeams();
        
        // 4. ZIP-Inhalt analysieren
        $file_list = [];
        $has_status_files = false;
        $has_team_structure = false;
        $has_user_structure = false;
        $found_assignment_reference = false;
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $file_list[] = $filename;
            
            $this->logger->info("ZIP file $i: '$filename'");
            
            // Status-Files prüfen (erweitert)
            $basename = basename($filename);
            if (in_array($basename, ['status.xlsx', 'status.csv', 'status.xls', 'batch_status.xlsx', 'batch_status.csv'])) {
                $has_status_files = true;
                $this->logger->info("✅ Found status file: $basename in $filename");
            }
            
            // Team-Struktur prüfen (Team_X/ Ordner)
            if (preg_match('/Team_\d+\//', $filename)) {
                $has_team_structure = true;
                $this->logger->info("✅ Found team structure: $filename");
            }
            
            // User-Struktur prüfen (Lastname_Firstname_Login_ID/ Ordner)
            if (preg_match('/[^\/]+_[^\/]+_[^\/]+_\d+\//', $filename)) {
                $has_user_structure = true;
                $this->logger->info("✅ Found user structure: $filename");
            }
            
            // Assignment-Referenz im Dateinamen prüfen
            if (strpos($filename, "Multi_Feedback_") !== false || 
                strpos($filename, "Batch_Feedback_") !== false ||
                strpos($filename, "_$assignment_id") !== false ||
                strpos($filename, "multi_feedback_") !== false) {
                $found_assignment_reference = true;
                $this->logger->info("✅ Found assignment reference: $filename");
            }
        }
        
        $zip->close();
        
        $this->logger->info("ZIP analysis results: status_files=$has_status_files, team_structure=$has_team_structure, user_structure=$has_user_structure, assignment_ref=$found_assignment_reference");
        
        // 5. Validierungen durchführen
        
        // Keine Status-Files gefunden
        if (!$has_status_files) {
            $this->logger->error("No status files found. Available files: " . implode(', ', array_map('basename', $file_list)));
            throw new Exception("Keine Status-Dateien (status.xlsx, status.csv) im ZIP gefunden. Dies scheint keine Multi-Feedback ZIP-Datei zu sein.");
        }
        
        // Team-Assignment aber keine Team-Struktur
        if ($is_team_assignment && !$has_team_structure) {
            throw new Exception("Dies ist ein Team-Assignment, aber das ZIP enthält keine Team-Ordner (Team_X/). Möglicherweise wurde die falsche ZIP-Datei hochgeladen.");
        }
        
        // Individual-Assignment aber Team-Struktur gefunden
        if (!$is_team_assignment && $has_team_structure) {
            throw new Exception("Dies ist ein Individual-Assignment, aber das ZIP enthält Team-Ordner. Möglicherweise wurde eine ZIP-Datei von einem anderen Assignment hochgeladen.");
        }
        
        // Keine User-Struktur gefunden
        if (!$has_user_structure) {
            throw new Exception("Keine User-Ordner (Lastname_Firstname_Login_ID/) im ZIP gefunden. Dies scheint keine gültige Multi-Feedback ZIP-Datei zu sein.");
        }
        
        // Assignment-Titel im ZIP-Inhalt prüfen (optional, da nicht immer zuverlässig)
        if (!$found_assignment_reference) {
            $this->logger->warning("Keine Assignment-Referenz im ZIP gefunden - könnte von anderem Assignment stammen");
        }
        
        $this->logger->info("ZIP-Validierung erfolgreich für Assignment $assignment_id");
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
            throw new Exception("Die hochgeladene Datei ist zu klein, um ein gültiges ZIP zu sein.");
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
        
        $this->logger->info("Searching for status files in " . count($extracted_files) . " extracted files:");
        
        foreach ($extracted_files as $file) {
            $original_name = $file['original_name'];
            $basename = basename($original_name);
            
            $this->logger->info("Checking file: '$original_name' -> basename: '$basename'");
            
            // Erweiterte Suche nach Status-Files
            if (in_array($basename, ['status.xlsx', 'status.csv', 'status.xls']) ||
                in_array($basename, ['batch_status.xlsx', 'batch_status.csv'])) {
                $status_files[] = $file['extracted_path'];
                $this->logger->info("✅ FOUND status file: " . $basename . " -> " . $file['extracted_path']);
            } else {
                $this->logger->info("❌ Not a status file: $basename");
            }
        }
        
        $this->logger->info("Total status files found: " . count($status_files));
        return $status_files;
    }
    
    /**
     * Verarbeitet Status-Files und wendet Updates an
     */
    private function processStatusFiles(array $status_files, int $assignment_id, bool $is_team): void
    {
        if (empty($status_files)) {
            throw new Exception("Keine gültigen Status-Dateien (status.xlsx, status.csv) in der ZIP gefunden. Bitte überprüfen Sie, ob die richtige ZIP-Datei hochgeladen wurde.");
        }
        
        try {
            $assignment = new \ilExAssignment($assignment_id);
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            $status_file->allowPlagiarismUpdate(true);
            
            $updates_applied = false;
            $load_errors = [];
            
            foreach ($status_files as $file_path) {
                if (!file_exists($file_path)) {
                    continue;
                }
                
                $this->logger->info("Processing status file: " . basename($file_path) . " (Size: " . filesize($file_path) . " bytes)");
                
                try {
                    $status_file->loadFromFile($file_path);
                    
                    if ($status_file->isLoadFromFileSuccess()) {
                        if ($status_file->hasUpdates()) {
                            $updates = $status_file->getUpdates();
                            $updates_count = count($updates);
                            
                            // DEBUG: Zeige gefundene Updates
                            $this->logger->info("Found $updates_count updates:");
                            foreach ($updates as $i => $update) {
                                $identifier = $update['login'] ?? $update['team_id'] ?? 'unknown';
                                $this->logger->info("Update $i: $identifier -> Status: {$update['status']}, Mark: {$update['mark']}, Comment: " . substr($update['comment'], 0, 50));
                            }
                            
                            $status_file->applyStatusUpdates();
                            
                            global $DIC;
                            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $status_file->getInfo(), true);
                            
                            $this->processing_stats['status_updates'] = $updates_count;
                            $updates_applied = true;
                            $this->logger->info("Successfully applied $updates_count status updates");
                            break;
                        } else {
                            $load_errors[] = "Keine Updates in " . basename($file_path) . " gefunden - möglicherweise wurden keine Änderungen vorgenommen oder die 'update' Spalte ist nicht auf 1 gesetzt.";
                            $this->logger->warning("No updates found in " . basename($file_path));
                        }
                    } else {
                        if ($status_file->hasError()) {
                            $load_errors[] = "Fehler beim Laden von " . basename($file_path) . ": " . $status_file->getInfo();
                            $this->logger->error("Error loading " . basename($file_path) . ": " . $status_file->getInfo());
                        } else {
                            $load_errors[] = "Datei " . basename($file_path) . " konnte nicht geladen werden - möglicherweise ungültiges Format oder falsche Assignment-ID.";
                            $this->logger->error("Failed to load " . basename($file_path));
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
                } else {
                    $error_msg .= "Die hochgeladene ZIP-Datei scheint nicht zu diesem Assignment zu gehören oder enthält keine gültigen Status-Änderungen.";
                }
                throw new Exception($error_msg);
            }
            
        } catch (Exception $e) {
            $this->logger->error("Error in status file processing: " . $e->getMessage());
            throw $e;
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
            $this->logger->warning("Could not cleanup temp directory $temp_dir: " . $e->getMessage());
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
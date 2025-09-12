<?php
declare(strict_types=1);

/**
 * Feedback Upload Handler - ZIP-Upload-Processing mit Debug
 * 
 * Verarbeitet hochgeladene Feedback-ZIPs und wendet Status-Updates an
 * Unterstützt sowohl Individual- als auch Team-Assignments
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - mit erweiterten Debug-Logs
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
        
        // Cleanup bei Script-Ende registrieren
        register_shutdown_function([$this, 'cleanupAllTempDirectories']);
    }
    
    /**
     * MAIN HANDLER: Feedback Upload Processing
     */
    public function handleFeedbackUpload(array $parameters): void
    {
        $assignment_id = $parameters['assignment_id'] ?? 0;
        $tutor_id = $parameters['tutor_id'] ?? 0;
        
        if (!$assignment_id) {
            $this->logger->warning("Plugin Upload: Missing assignment_id");
            return;
        }
        
        try {
            $assignment = new \ilExAssignment($assignment_id);
            $zip_content = $this->extractZipContent($parameters);
            
            if (!$zip_content || !$this->isValidZipContent($zip_content)) {
                $this->logger->warning("Plugin Upload: Invalid or missing ZIP content");
                return;
            }
            
            $this->logger->info("Plugin Upload: Processing feedback upload for assignment $assignment_id");
            
            // Team vs Individual Processing
            if ($assignment->getAssignmentType()->usesTeams()) {
                $this->processTeamUpload($zip_content, $assignment_id, $tutor_id);
            } else {
                $this->processIndividualUpload($zip_content, $assignment_id, $tutor_id);
            }
            
            // Session-Flag setzen für Erfolgsmeldung
            $this->setProcessingSuccess($assignment_id, $tutor_id);
            
            $this->logger->info("Plugin Upload: Successfully processed feedback upload");
            
        } catch (Exception $e) {
            $this->logger->error("Plugin Upload: Error processing upload: " . $e->getMessage());
        }
    }
    
    /**
     * Team Assignment Upload Processing - MIT DEBUG
     */
    private function processTeamUpload(string $zip_content, int $assignment_id, int $tutor_id): void
    {
        $this->logger->info("Plugin Upload: Processing team assignment upload");
        
        $temp_zip = $this->createTempZipFile($zip_content, 'team_feedback');
        if (!$temp_zip) return;
        
        try {
            $extracted_files = $this->extractZipContents($temp_zip, 'team_extract');
            
            // DEBUG: Zeige alle gefundenen Dateien
            $this->logger->info("Plugin Upload: DEBUG - Extracted " . count($extracted_files) . " files:");
            foreach ($extracted_files as $index => $file) {
                $this->logger->info("Plugin Upload: DEBUG - File $index: '" . $file['original_name'] . "' (size: " . $file['size'] . " bytes)");
            }
            
            $status_files = $this->findStatusFiles($extracted_files);
            
            // DEBUG: Zeige gefundene Status-Files
            $this->logger->info("Plugin Upload: DEBUG - Found " . count($status_files) . " status files:");
            foreach ($status_files as $index => $status_file_path) {
                $this->logger->info("Plugin Upload: DEBUG - Status file $index: '$status_file_path'");
            }
            
            if (!empty($status_files)) {
                $this->logger->info("Plugin Upload: Processing " . count($status_files) . " status files");
                $this->processStatusFiles($status_files, $assignment_id, true);
            } else {
                $this->logger->warning("Plugin Upload: No status files found - looking for files matching: status.xlsx, status.csv, status.xls, batch_status.xlsx, batch_status.csv");
            }
            
            // Team-spezifische Feedback-Files verarbeiten
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
        $this->logger->info("Plugin Upload: Processing individual assignment upload");
        
        $temp_zip = $this->createTempZipFile($zip_content, 'individual_feedback');
        if (!$temp_zip) return;
        
        try {
            $extracted_files = $this->extractZipContents($temp_zip, 'individual_extract');
            $status_files = $this->findStatusFiles($extracted_files);
            
            if (!empty($status_files)) {
                $this->processStatusFiles($status_files, $assignment_id, false);
            }
            
            // Individual Feedback-Files verarbeiten
            $this->processIndividualFeedbackFiles($extracted_files, $assignment_id);
            
        } finally {
            $this->cleanupTempFile($temp_zip);
        }
    }
    
    /**
     * ZIP-Content aus Upload-Parameters extrahieren
     */
    private function extractZipContent(array $parameters): ?string
    {
        // Direkt als String übergeben
        if (isset($parameters['zip_content']) && is_string($parameters['zip_content'])) {
            return $parameters['zip_content'];
        }
        
        // Aus Upload-Result extrahieren
        if (isset($parameters['upload_result'])) {
            return $this->getZipContentFromUploadResult($parameters['upload_result']);
        }
        
        // Aus File-Path laden
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
        return strlen($content) > 100 && substr($content, 0, 2) === 'PK';
    }
    
    /**
     * Erstellt temporäre ZIP-Datei
     */
    private function createTempZipFile(string $zip_content, string $prefix): ?string
    {
        $temp_zip = sys_get_temp_dir() . '/plugin_' . $prefix . '_' . uniqid() . '.zip';
        
        if (file_put_contents($temp_zip, $zip_content) === false) {
            $this->logger->error("Plugin Upload: Could not create temp ZIP file");
            return null;
        }
        
        if (!file_exists($temp_zip) || filesize($temp_zip) < 100) {
            $this->logger->error("Plugin Upload: Invalid temp ZIP file created");
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
            $this->logger->error("Plugin Upload: Could not open ZIP file");
            return [];
        }
        
        $extract_dir = $this->createTempDirectory($extract_prefix);
        $extracted_files = [];
        
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (empty($filename) || substr($filename, -1) === '/') continue; // Skip directories
                
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
            
            $this->logger->info("Plugin Upload: Extracted " . count($extracted_files) . " files from ZIP");
            
        } finally {
            $zip->close();
        }
        
        return $extracted_files;
    }
    
    /**
     * Findet Status-Files in extrahierten Dateien - MIT DEBUG
     */
    private function findStatusFiles(array $extracted_files): array
    {
        $status_files = [];
        
        $this->logger->info("Plugin Upload: DEBUG - Searching for status files in " . count($extracted_files) . " extracted files");
        
        foreach ($extracted_files as $file) {
            $basename = basename($file['original_name']);
            $this->logger->info("Plugin Upload: DEBUG - Checking file: '" . $file['original_name'] . "' -> basename: '$basename'");
            
            if (in_array($basename, ['status.xlsx', 'status.csv', 'status.xls', 'batch_status.xlsx', 'batch_status.csv'])) {
                $status_files[] = $file['extracted_path'];
                $this->logger->info("Plugin Upload: DEBUG - ✅ FOUND status file: " . $basename . " -> " . $file['extracted_path']);
            } else {
                $this->logger->info("Plugin Upload: DEBUG - ❌ Not a status file: $basename");
            }
        }
        
        return $status_files;
    }
    
    /**
     * Verarbeitet Status-Files und wendet Updates an - MIT DEBUG
     */
    private function processStatusFiles(array $status_files, int $assignment_id, bool $is_team): void
    {
        if (empty($status_files)) {
            $this->logger->info("Plugin Upload: No status files to process");
            return;
        }
        
        $this->logger->info("Plugin Upload: DEBUG - Starting to process " . count($status_files) . " status files for assignment $assignment_id (team: " . ($is_team ? 'yes' : 'no') . ")");
        
        try {
            $assignment = new \ilExAssignment($assignment_id);
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            $status_file->allowPlagiarismUpdate(true);
            
            $updates_applied = false;
            
            foreach ($status_files as $index => $file_path) {
                if (!file_exists($file_path)) {
                    $this->logger->error("Plugin Upload: DEBUG - Status file does not exist: $file_path");
                    continue;
                }
                
                $file_size = filesize($file_path);
                $this->logger->info("Plugin Upload: DEBUG - Processing status file $index: " . basename($file_path) . " (size: $file_size bytes, path: $file_path)");
                
                try {
                    $status_file->loadFromFile($file_path);
                    
                    if ($status_file->isLoadFromFileSuccess()) {
                        $this->logger->info("Plugin Upload: DEBUG - ✅ Successfully loaded status file: " . basename($file_path));
                        
                        if ($status_file->hasUpdates()) {
                            $updates_count = count($status_file->getUpdates());
                            $this->logger->info("Plugin Upload: DEBUG - Found $updates_count updates in status file");
                            
                            $status_file->applyStatusUpdates();
                            
                            // Success-Message setzen
                            global $DIC;
                            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $status_file->getInfo(), true);
                            
                            $updates_applied = true;
                            $this->processing_stats['status_updates'] = $updates_count;
                            
                            $this->logger->info("Plugin Upload: DEBUG - ✅ Successfully applied $updates_count status updates from " . basename($file_path));
                            break; // Nur eine Status-Datei verarbeiten
                        } else {
                            $this->logger->warning("Plugin Upload: DEBUG - No updates found in status file: " . basename($file_path));
                        }
                    } else {
                        $this->logger->error("Plugin Upload: DEBUG - ❌ Failed to load status file: " . basename($file_path));
                        if ($status_file->hasError()) {
                            $this->logger->error("Plugin Upload: DEBUG - Status file error: " . $status_file->getInfo());
                        }
                    }
                    
                } catch (Exception $e) {
                    $this->logger->error("Plugin Upload: DEBUG - Exception processing status file " . basename($file_path) . ": " . $e->getMessage());
                }
            }
            
            if (!$updates_applied) {
                $this->logger->warning("Plugin Upload: DEBUG - No status updates were applied from any status file");
            }
            
        } catch (Exception $e) {
            $this->logger->error("Plugin Upload: DEBUG - Error in status file processing: " . $e->getMessage());
        }
    }
    
    /**
     * Verarbeitet Team-spezifische Feedback-Files
     */
    private function processTeamFeedbackFiles(array $extracted_files, int $assignment_id): void
    {
        $team_feedback_files = $this->findTeamFeedbackFiles($extracted_files);
        
        if (empty($team_feedback_files)) {
            $this->logger->info("Plugin Upload: No team feedback files found");
            return;
        }
        
        $this->logger->info("Plugin Upload: Processing " . count($team_feedback_files) . " team feedback files");
        
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
            $this->logger->info("Plugin Upload: No individual feedback files found");
            return;
        }
        
        $this->logger->info("Plugin Upload: Processing " . count($individual_feedback_files) . " individual feedback files");
        
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
            
            // Pattern: Team_X/User_Dir/feedback_file
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
            
            // Pattern: Lastname_Firstname_Login_UserID/feedback_file
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
        $this->logger->info("Plugin Upload: Processing feedback for team $team_id");
        
        // TODO: Hier könnte in Phase 4 erweiterte Team-Feedback-Logic implementiert werden
        // Zum Beispiel:
        // - Feedback-Files in ILIAS-Storage kopieren
        // - Team-Notifications versenden
        // - Team-spezifische Kommentare verarbeiten
        
        foreach ($files as $file) {
            $this->logger->debug("Plugin Upload: Team $team_id file: " . $file['filename']);
        }
    }
    
    /**
     * Verarbeitet User-spezifisches Feedback
     */
    private function processUserSpecificFeedback(int $user_id, array $files, int $assignment_id): void
    {
        $this->logger->info("Plugin Upload: Processing feedback for user $user_id");
        
        // TODO: Hier könnte erweiterte Individual-Feedback-Logic implementiert werden
        
        foreach ($files as $file) {
            $this->logger->debug("Plugin Upload: User $user_id file: " . $file['filename']);
        }
    }
    
    /**
     * Setzt Processing-Success-Flag
     */
    private function setProcessingSuccess(int $assignment_id, int $tutor_id): void
    {
        $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] = time();
        $_SESSION['exc_status_files_stats'][$assignment_id][$tutor_id] = $this->processing_stats;
        
        $this->logger->info("Plugin Upload: Set processing success flag for assignment $assignment_id, tutor $tutor_id");
    }
    
    /**
     * UTILITY: Temp-Directory erstellen
     */
    private function createTempDirectory(string $prefix): string
    {
        $temp_dir = sys_get_temp_dir() . '/plugin_' . $prefix . '_' . uniqid();
        mkdir($temp_dir, 0777, true);
        $this->temp_directories[] = $temp_dir;
        
        return $temp_dir;
    }
    
    /**
     * UTILITY: Temp-File aufräumen
     */
    private function cleanupTempFile(string $file_path): void
    {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    /**
     * CLEANUP: Alle Temp-Directories aufräumen
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
     * CLEANUP: Einzelnes Temp-Directory aufräumen
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
            $this->logger->warning("Plugin Upload: Could not cleanup temp directory $temp_dir: " . $e->getMessage());
        }
    }
    
    /**
     * DEBUG: Get Processing Statistics
     */
    public function getProcessingStats(): array
    {
        return $this->processing_stats;
    }
}
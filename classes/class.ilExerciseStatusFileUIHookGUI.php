<?php
declare(strict_types=1);

class ilExerciseStatusFileUIHookGUI extends ilUIHookPluginGUI
{
    protected ilExerciseStatusFilePlugin $plugin;

    public function __construct(ilExerciseStatusFilePlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        $return = ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];

        if ($a_comp === "Modules/Exercise") {
            switch ($a_part) {
                case "tutor_feedback_download":
                    $this->handleFeedbackDownload($a_par);
                    break;
                case "tutor_feedback_processing":
                    $this->handleFeedbackProcessing($a_par);
                    break;
            }
        }

        return $return;
    }

    protected function handleFeedbackDownload(array $a_par): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        if (!isset($a_par['assignment']) || !isset($a_par['members']) || !isset($a_par['zip'])) {
            $logger->warning("Plugin StatusFile: Missing parameters for feedback download");
            return;
        }

        try {
            $assignment = $a_par['assignment'];
            $members = $a_par['members'];
            $zip = &$a_par['zip'];
            
            $logger->info("Plugin StatusFile: Before adding status files - ZIP has " . $zip->numFiles . " files");
            
            $this->addStatusFilesToZip($zip, $assignment, $members);
            
            $logger->info("Plugin StatusFile: After adding status files - ZIP has " . $zip->numFiles . " files");
            
            // Debug ZIP-Inhalte
            $this->debugZipContents($zip);
            
        } catch (Exception $e) {
            $logger->error("Plugin StatusFile: Error adding status files to ZIP: " . $e->getMessage());
        }
    }

    protected function handleFeedbackUpload(array $a_par): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        if (!isset($a_par['assignment_id'])) {
            $logger->warning("Missing assignment_id for feedback upload");
            return;
        }

        $assignment_id = $a_par['assignment_id'];
        $tutor_id = $a_par['tutor_id'] ?? 0;
        
        // Prüfung ob bereits verarbeitet
        $recent_processing = $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] ?? 0;
        if (time() - $recent_processing < 300) {
            $logger->info("Status files recently processed - skipping fallback");
            return;
        }
        
        $logger->info("No recent processing found - ZIP likely already processed by processing hook");
    }

    /**
     * NEUER HOOK: Für Verarbeitung mit direktem ZIP-Content
     */
    protected function handleFeedbackProcessing(array $a_par): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $assignment_id = $a_par['assignment_id'] ?? 0;
        $tutor_id = $a_par['tutor_id'] ?? 0;
        
        if (!$assignment_id) {
            $logger->warning("Missing assignment_id");
            return;
        }
        
        try {            
            $zip_content = null;
            
            // Direkter ZIP-Content verfügbar?
            if (isset($a_par['zip_content']) && is_string($a_par['zip_content'])) {
                $zip_content = $a_par['zip_content'];
            }
            // UploadResult verfügbar?
            elseif (isset($a_par['upload_result'])) {
                $upload_result = $a_par['upload_result'];
                
                // ILIAS UploadResult Zugriff
                if (method_exists($upload_result, 'getPath') && $upload_result->getPath()) {
                    $temp_path = $upload_result->getPath();
                    if (file_exists($temp_path)) {
                        $zip_content = file_get_contents($temp_path);
                    }
                } elseif (method_exists($upload_result, 'getName')) {
                    // Fallback über $_FILES
                    $filename = $upload_result->getName();
                    foreach ($_FILES as $file_info) {
                        if (isset($file_info['name']) && $file_info['name'] === $filename && 
                            isset($file_info['tmp_name']) && file_exists($file_info['tmp_name'])) {
                            $zip_content = file_get_contents($file_info['tmp_name']);
                            break;
                        }
                    }
                }
            }
            
            if ($zip_content && $this->isZipContent($zip_content)) {
                $this->processZipContent($zip_content, $assignment_id, $tutor_id);
                $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] = time();
                $logger->info("Status files processed successfully via processing hook");
            } else {
                $logger->info("No valid ZIP content found in processing hook");
            }
            
        } catch (Exception $e) {
            $logger->error("Error in feedback processing hook: " . $e->getMessage());
        }
    }

    /**
     * NEU: Hole ZIP-Content direkt aus Repository
     */
    protected function getZipContentFromRepository($zip_repo, int $assignment_id, int $tutor_id, $stakeholder): ?string
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            
            // Prüfe ob ZIP existiert
            if (method_exists($zip_repo, 'hasFile')) {
                $has_file = $zip_repo->hasFile($assignment_id, $tutor_id, $stakeholder);
                if (!$has_file) {
                    $logger->info("Repository reports no ZIP file exists");
                    return null;
                }
            }
            
            // Versuche verschiedene Repository-Methoden
            $methods_to_try = [
                'getCurrent' => [$assignment_id, $tutor_id, $stakeholder],
                'getContent' => [$assignment_id, $tutor_id, $stakeholder],
                'getStream' => [$assignment_id, $tutor_id, $stakeholder]
            ];
            
            foreach ($methods_to_try as $method => $params) {
                if (method_exists($zip_repo, $method)) {
                    try {
                        $logger->info("Trying repository method: $method");
                        $result = call_user_func_array([$zip_repo, $method], $params);
                        
                        if ($result) {
                            // String-Content direkt zurückgeben
                            if (is_string($result) && $this->isZipContent($result)) {
                                $logger->info("Got ZIP content from repository method: $method");
                                return $result;
                            }
                            
                            // Stream-Content extrahieren
                            if (is_object($result) && method_exists($result, 'read')) {
                                $result->rewind();
                                $content = '';
                                while (!$result->eof()) {
                                    $content .= $result->read(8192);
                                }
                                if ($this->isZipContent($content)) {
                                    $logger->info("Got ZIP content from repository stream: $method");
                                    return $content;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $logger->info("Repository method $method failed: " . $e->getMessage());
                    }
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $logger->error("Error accessing ZIP repository: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback: Hole ZIP-Content aus Upload
     */
    protected function getUploadedZipContent(): ?string
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            // 1. Prüfe $_FILES
            foreach ($_FILES as $field_name => $file_info) {
                if (isset($file_info['tmp_name']) && 
                    is_uploaded_file($file_info['tmp_name']) && 
                    $file_info['error'] === UPLOAD_ERR_OK) {
                    
                    $content = file_get_contents($file_info['tmp_name']);
                    if ($content && $this->isZipContent($content)) {
                        $logger->info("Found ZIP in \$_FILES['$field_name']");
                        return $content;
                    }
                }
            }
            
            // 2. Prüfe ILIAS HTTP Request
            $http = $DIC->http();
            $request = $http->request();
            $uploaded_files = $request->getUploadedFiles();
            
            foreach ($uploaded_files as $field_name => $upload_file) {
                if ($upload_file->getError() === UPLOAD_ERR_OK) {
                    $stream = $upload_file->getStream();
                    $content = $stream->getContents();
                    
                    if ($content && $this->isZipContent($content)) {
                        $logger->info("Found ZIP in HTTP upload: $field_name");
                        return $content;
                    }
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $logger->error("Error getting uploaded ZIP content: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Prüfe ob Content eine ZIP-Datei ist
     */
    protected function isZipContent(string $content): bool
    {
        return strlen($content) > 100 && substr($content, 0, 2) === 'PK';
    }

    /**
     * ZIP-Content verarbeiten
     */
    protected function processZipContent(string $zip_content, int $assignment_id, int $tutor_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {            
            // Temporäre ZIP-Datei erstellen
            $temp_zip = sys_get_temp_dir() . '/plugin_feedback_' . uniqid() . '.zip';
            file_put_contents($temp_zip, $zip_content);
            
            if (file_exists($temp_zip) && filesize($temp_zip) > 100) {
                $this->processStatusFilesFromZip($temp_zip, $assignment_id, $tutor_id);
            }
            
            // Cleanup
            if (file_exists($temp_zip)) {
                unlink($temp_zip);
            }
            
        } catch (Exception $e) {
            $logger->error("Error processing ZIP content: " . $e->getMessage());
        }
    }

    protected function processStatusFilesFromZip(string $zip_path, int $assignment_id, int $tutor_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();        
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            $logger->error("Cannot open ZIP file: $zip_path");
            return;
        }
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_extract_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        $status_files_found = [];
        
        try {
            // Suche nach Status-Dateien
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                if (in_array($filename, ['status.xlsx', 'status.csv'])) {
                    $zip->extractTo($tmp_dir, $filename);
                    $extracted_path = $tmp_dir . '/' . $filename;
                    
                    if (file_exists($extracted_path)) {
                        $status_files_found[] = $extracted_path;
                    }
                }
            }
            
            $zip->close();
            
            // Verarbeite gefundene Status-Dateien
            if (!empty($status_files_found)) {
                $this->processExtractedStatusFiles($status_files_found, $assignment_id);
            } else {
                $logger->info("No status files found in ZIP");
            }
            
        } finally {
            $this->cleanupTempDir($tmp_dir);
        }
    }

    protected function processExtractedStatusFiles(array $status_files, int $assignment_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $assignment = new \ilExAssignment($assignment_id);
        $status_file = new ilPluginExAssignmentStatusFile();
        $status_file->init($assignment);
        $status_file->allowPlagiarismUpdate(true);
        
        $processed = false;
        
        foreach ($status_files as $file_path) {
            if (!file_exists($file_path)) {
                continue;
            }
            
            try {
                $status_file->loadFromFile($file_path);
                
                if ($status_file->isLoadFromFileSuccess()) {
                    if ($status_file->hasUpdates()) {
                        $status_file->applyStatusUpdates();                        
                        // Success message
                        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $status_file->getInfo(), true);
                        $processed = true;
                        break;
                    } else {
                        $logger->info("No updates found in: " . basename($file_path));
                    }
                } else {
                    $logger->warning("Failed to load: " . basename($file_path));
                    if ($status_file->hasError()) {
                        $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $status_file->getInfo(), true);
                    }
                }
            } catch (Exception $e) {
                $logger->error("Error processing " . basename($file_path) . ": " . $e->getMessage());
            }
        }
        
        if (!$processed) {
            $logger->info("No status files could be processed successfully");
        }
    }

    protected function addStatusFilesToZip(\ZipArchive &$zip, \ilExAssignment $assignment, array $members): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_status_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        try {
            $user_ids = is_array($members) ? array_keys($members) : $members;
            
            // Base-Name berechnen (wie im TutorFeedbackZipManager)
            $base_name = trim(str_replace(" ", "_", $assignment->getTitle() . "_" . $assignment->getId()));
            $base_name = "multi_feedback_" . $this->toAscii($base_name);
            
            $logger->info("Plugin StatusFile: Using base name: $base_name");
            
            // XLSX Status File
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_XML);
            $xlsx_path = $tmp_dir . '/status.xlsx';
            $status_file->writeToFile($xlsx_path);
            
            if ($status_file->isWriteToFileSuccess() && file_exists($xlsx_path)) {
                // Status-Datei im ROOT des ZIP (nicht im base_name Ordner)
                $zip->addFile($xlsx_path, "status.xlsx");
                $logger->info("Added status.xlsx to ZIP root");
                $logger->info("File size: " . filesize($xlsx_path) . " bytes");
            } else {
                $logger->error("Failed to create or find XLSX file: $xlsx_path");
            }
            
            // CSV Status File
            $csv_status_file = new ilPluginExAssignmentStatusFile();
            $csv_status_file->init($assignment);
            $csv_status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_CSV);
            $csv_path = $tmp_dir . '/status.csv';
            $csv_status_file->writeToFile($csv_path);
            
            if ($csv_status_file->isWriteToFileSuccess() && file_exists($csv_path)) {
                // Status-Datei im ROOT des ZIP (nicht im base_name Ordner)
                $zip->addFile($csv_path, "status.csv");
                $logger->info("Added status.csv to ZIP root");
                $logger->info("File size: " . filesize($csv_path) . " bytes");
            } else {
                $logger->error("Failed to create or find CSV file: $csv_path");
            }
            
            $logger->info("Plugin StatusFile: Successfully completed addStatusFilesToZip");
            
        } catch (Exception $e) {
            $logger->error("Plugin StatusFile: Error in addStatusFilesToZip: " . $e->getMessage());
            // Bei Fehler: Sofort aufräumen
            $this->cleanupTempDir($tmp_dir);
            throw $e;
        }
        
        // WICHTIG: Cleanup erst NACH dem ZIP->close() 
        // Verwende register_shutdown_function für sicheres Cleanup
        $this->registerShutdownCleanup($tmp_dir);
    }

    /**
     * ASCII-Konvertierung wie im TutorFeedbackZipManager
     */
    protected function toAscii(string $filename): string
    {
        global $DIC;
        return (new \ilFileServicesPolicy($DIC->fileServiceSettings()))->ascii($filename);
    }

    /**
     * Zusätzliche Debug-Funktion für ZIP-Inhalte
     */
    protected function debugZipContents(\ZipArchive $zip): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $logger->info("Plugin StatusFile: ZIP Debug - Number of files: " . $zip->numFiles);
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat) {
                $logger->info("Plugin StatusFile: ZIP file $i: " . 
                    $stat['name'] . " (size: " . $stat['size'] . " bytes)");
            }
        }
    }

    /**
     * Sicheres Cleanup über register_shutdown_function
     */
    protected function registerShutdownCleanup(string $tmp_dir): void
    {
        register_shutdown_function(function() use ($tmp_dir) {
            if (is_dir($tmp_dir)) {
                $this->cleanupTempDir($tmp_dir);
            }
        });
    }

    protected function cleanupTempDir(string $tmp_dir): void
    {
        if (is_dir($tmp_dir)) {
            $files = glob($tmp_dir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($tmp_dir);
        }
    }

    public function __destruct()
    {
        if (isset($_SESSION['plugin_temp_cleanup'])) {
            foreach ($_SESSION['plugin_temp_cleanup'] as $tmp_dir) {
                $this->cleanupTempDir($tmp_dir);
            }
            unset($_SESSION['plugin_temp_cleanup']);
        }
    }
}
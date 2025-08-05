<?php
declare(strict_types=1);

class ilExerciseStatusFileUIHookGUI extends ilUIHookPluginGUI
{
    protected ilExerciseStatusFilePlugin $plugin;

    public function __construct(ilExerciseStatusFilePlugin $plugin)
    {
        $this->plugin = $plugin;
        #parent::__construct();
    }

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        global $DIC;
        $logger = $DIC->logger()->root();

        $return = ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];

        //$logger->info("ExerciseStatusFile Plugin Hook called: $a_comp / $a_part");

        if ($a_comp === "Modules/Exercise") {
            switch ($a_part) {
                case "tutor_feedback_download":
                    $this->handleFeedbackDownload($a_par);
                    break;
                    
                case "tutor_feedback_upload":
                    $this->handleFeedbackUpload($a_par);
                    break;
            }
        }

        return $return;
    }

    protected function handleFeedbackDownload(array $a_par): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();

        $logger->info("ExerciseStatusFile Plugin: Handling feedback download");
        
        if (!isset($a_par['assignment']) || !isset($a_par['members']) || !isset($a_par['zip'])) {
            $logger->warning("Missing parameters for feedback download");
            return;
        }

        try {
            $assignment = $a_par['assignment'];
            $members = $a_par['members'];
            $zip = &$a_par['zip'];
            
            $this->addStatusFilesToZip($zip, $assignment, $members);
            
            $logger->info("Status files successfully added to download ZIP");
        } catch (Exception $e) {
            $logger->error("Error adding status files to ZIP: " . $e->getMessage());
        }
    }

    protected function handleFeedbackUpload(array $a_par): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $logger->info("ExerciseStatusFile Plugin: Handling feedback upload");
        
        if (!isset($a_par['assignment_id'])) {
            $logger->warning("Missing assignment_id for feedback upload");
            return;
        }

        try {
            $assignment_id = $a_par['assignment_id'];
            $tutor_id = $a_par['tutor_id'] ?? 0;
            
            $recent_processing = $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] ?? 0;
            if (time() - $recent_processing < 300) {
                $logger->info("Status files recently processed by plugin - skipping");
                return;
            }
            
            $this->processStatusFilesFromUpload($assignment_id, $tutor_id);
            
            $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] = time();
            
            $logger->info("Status files successfully processed from upload");
        } catch (Exception $e) {
            $logger->error("Error processing status files from upload: " . $e->getMessage());
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
            
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            
            $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_XML);
            $xlsx_path = $tmp_dir . '/status.xlsx';
            $status_file->writeToFile($xlsx_path);
            
            if ($status_file->isWriteToFileSuccess()) {
                $zip->addFile($xlsx_path, "status.xlsx");
                $logger->info("Plugin: Added status.xlsx to ZIP");
            }
            
            $csv_status_file = new ilPluginExAssignmentStatusFile();
            $csv_status_file->init($assignment, $user_ids);
            $csv_status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_CSV);
            $csv_path = $tmp_dir . '/status.csv';
            $csv_status_file->writeToFile($csv_path);
            
            if ($csv_status_file->isWriteToFileSuccess()) {
                $zip->addFile($csv_path, "status.csv");
                $logger->info("Plugin: Added status.csv to ZIP");
            }
            
        } finally {
            $_SESSION['plugin_temp_cleanup'][] = $tmp_dir;
        }
    }

    protected function processStatusFilesFromUpload(int $assignment_id, int $tutor_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $zip_path = $this->getCurrentUploadZipPath($assignment_id, $tutor_id);
        
        if (!$zip_path || !file_exists($zip_path)) {
            $logger->info("No ZIP file found for processing");
            return;
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            $logger->error("Cannot open ZIP file: " . $zip_path);
            return;
        }
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_extract_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        $status_file_found = false;
        
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                if (in_array($filename, ['status.xlsx', 'status.csv'])) {
                    $zip->extractTo($tmp_dir, $filename);
                    $status_file_found = true;
                    $logger->info("Found and extracted status file: " . $filename);
                }
            }
            
            $zip->close();
            
            if ($status_file_found) {
                $this->processExtractedStatusFiles($tmp_dir, $assignment_id);
            }
            
        } finally {
            $this->cleanupTempDir($tmp_dir);
        }
    }

    protected function processExtractedStatusFiles(string $tmp_dir, int $assignment_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $assignment = new \ilExAssignment($assignment_id);
        $status_file = new ilPluginExAssignmentStatusFile();
        $status_file->init($assignment);
        $status_file->allowPlagiarismUpdate(true);
        
        $files_to_try = [
            $tmp_dir . '/status.xlsx',
            $tmp_dir . '/status.csv'
        ];
        
        foreach ($files_to_try as $file_path) {
            if (file_exists($file_path)) {
                $logger->info("Processing status file: " . basename($file_path));
                
                $status_file->loadFromFile($file_path);
                
                if ($status_file->isLoadFromFileSuccess()) {
                    if ($status_file->hasUpdates()) {
                        $status_file->applyStatusUpdates();
                        $logger->info("Status updates applied successfully from: " . basename($file_path));
                        
                        // Success message für User über ILIAS 9 Message System
                        global $DIC;
                        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $status_file->getInfo(), true);
                        break;
                    } else {
                        $logger->info("No updates found in: " . basename($file_path));
                    }
                } else {
                    $logger->warning("Failed to load status file: " . basename($file_path));
                    if ($status_file->hasError()) {
                        // Failure message für User über ILIAS 9 Message System
                        global $DIC;
                        $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $status_file->getInfo(), true);
                    }
                }
            }
        }
    }

    protected function getCurrentUploadZipPath(int $assignment_id, int $tutor_id): ?string
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            // ILIAS 9 Filesystem Service verwenden
            $stakeholder = new \ilExcTutorFeedbackZipStakeholder();
            
            // Verschiedene mögliche Methoden versuchen
            if (method_exists($stakeholder, 'getPath')) {
                $path = $stakeholder->getPath($assignment_id, $tutor_id);
            } elseif (method_exists($stakeholder, 'getAbsolutePath')) {
                $path = $stakeholder->getAbsolutePath($assignment_id, $tutor_id);
            } else {
                // Alternative: über Repository-Service
                $repo = new \ILIAS\Exercise\TutorFeedbackFile\TutorFeedbackZipRepository(
                    $DIC->fileSystem()->storage(),
                    $stakeholder
                );
                
                // Versuche aktuellste Datei zu finden
                $files = $repo->getFiles($assignment_id, $tutor_id, []);
                if (!empty($files)) {
                    $latest_file = array_pop($files);
                    $path = $latest_file['full_path'] ?? null;
                } else {
                    $path = null;
                }
            }
            
            if ($path && file_exists($path)) {
                $logger->info("Plugin: Found ZIP path: " . $path);
                return $path;
            }
            
            $logger->info("Plugin: No valid ZIP path found for assignment $assignment_id, tutor $tutor_id");
            return null;
            
        } catch (Exception $e) {
            $logger->error("Plugin: Error getting ZIP path: " . $e->getMessage());
            return null;
        }
    }

    protected function cleanupTempDir(string $tmp_dir): void
    {
        if (is_dir($tmp_dir)) {
            $files = glob($tmp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
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
?>
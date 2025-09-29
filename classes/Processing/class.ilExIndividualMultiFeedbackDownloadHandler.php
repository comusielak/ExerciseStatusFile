<?php
declare(strict_types=1);

/**
 * Individual Multi-Feedback Download Handler
 * 
 * Verarbeitet Multi-User-Downloads für Individual-Assignments
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExIndividualMultiFeedbackDownloadHandler
{
    private ilLogger $logger;
    private array $temp_directories = [];
    private ilExUserDataProvider $user_provider;
    private ilExerciseStatusFilePlugin $plugin;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->user_provider = new ilExUserDataProvider();
        
        // Plugin-Instanz für Übersetzungen
        $plugin_id = 'exstatusfile';

        $repo = $DIC['component.repository'];
        $factory = $DIC['component.factory'];

        $info = $repo->getPluginById($plugin_id);
        if ($info !== null && $info->isActive()) {
            $this->plugin = $factory->getPlugin();
        }
        
        register_shutdown_function([$this, 'cleanupAllTempDirectories']);
    }
    
    /**
     * Individual Multi-Feedback-Download für ausgewählte User generieren
     */
    public function generateIndividualMultiFeedbackDownload(int $assignment_id, array $user_ids): void
    {
        try {
            $assignment = new \ilExAssignment($assignment_id);
            
            // Nur für Individual-Assignments
            if ($assignment->getAssignmentType()->usesTeams()) {
                throw new Exception("Assignment $assignment_id is a team assignment");
            }
            
            $validated_users = $this->validateUsers($assignment_id, $user_ids);
            if (empty($validated_users)) {
                throw new Exception("No valid users found");
            }
            
            $zip_path = $this->createIndividualMultiFeedbackZIP($assignment, $validated_users);
            $this->sendZIPDownload($zip_path, $assignment, $validated_users);
            
        } catch (Exception $e) {
            $this->logger->error("Individual Multi-Feedback download error: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Users validieren
     */
    private function validateUsers(int $assignment_id, array $user_ids): array
    {
        $validated_users = [];
        $all_users = $this->user_provider->getUsersForAssignment($assignment_id);
        
        foreach ($user_ids as $user_id) {
            foreach ($all_users as $user_data) {
                if ($user_data['user_id'] == $user_id) {
                    $validated_users[] = $user_data;
                    break;
                }
            }
        }
        
        return $validated_users;
    }
    
    /**
     * Individual Multi-Feedback ZIP erstellen
     */
    private function createIndividualMultiFeedbackZIP(\ilExAssignment $assignment, array $users): string
    {
        $temp_dir = $this->createTempDirectory('individual_multi_feedback');
        $zip_filename = $this->generateZIPFilename($assignment, $users);
        $zip_path = $temp_dir . '/' . $zip_filename;
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE) !== true) {
            throw new Exception("Could not create ZIP file: $zip_path");
        }
        
        try {
            $this->addStatusFiles($zip, $assignment, $users, $temp_dir);
            $this->addUserSubmissionsFromArrays($zip, $assignment, $users);
            $this->addReadme($zip, $assignment, $users, $temp_dir);
            $this->addMetadata($zip, $assignment, $users, $temp_dir);
            
            $zip->close();
            return $zip_path;
            
        } catch (Exception $e) {
            $zip->close();
            throw $e;
        }
    }
    
    /**
     * User-Submissions aus Array-Daten hinzufügen
     */
    private function addUserSubmissionsFromArrays(\ZipArchive &$zip, \ilExAssignment $assignment, array $users_data): void
    {
        $base_name = $this->toAscii("Multi_Feedback_Individual_" . $assignment->getTitle() . "_" . $assignment->getId());
        
        foreach ($users_data as $user_data) {
            $user_id = $user_data['user_id'];
            $user_folder = $base_name . "/" . $this->generateUserFolderName($user_data);
            
            $zip->addEmptyDir($user_folder);
            $this->addUserInfoToZip($zip, $user_folder, $user_data);
            $this->addUserSubmissionsToZip($zip, $user_folder, $assignment, $user_id);
        }
    }
    
    /**
     * Status-Files erstellen
     */
    private function addStatusFiles(\ZipArchive &$zip, \ilExAssignment $assignment, array $users, string $temp_dir): void
    {
        $status_file = new ilPluginExAssignmentStatusFile();
        $status_file->init($assignment);
        
        // XLSX
        $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_XML);
        $xlsx_path = $temp_dir . '/status.xlsx';
        $status_file->writeToFile($xlsx_path);
        
        if ($status_file->isWriteToFileSuccess() && file_exists($xlsx_path)) {
            $zip->addFile($xlsx_path, "status.xlsx");
        }
        
        // CSV
        $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_CSV);
        $csv_path = $temp_dir . '/status.csv';
        $status_file->writeToFile($csv_path);
        
        if ($status_file->isWriteToFileSuccess() && file_exists($csv_path)) {
            $zip->addFile($csv_path, "status.csv");
        }
        
        // User-Info
        $user_info = $this->generateUserInfo($assignment, $users);
        $info_path = $temp_dir . '/user_info.json';
        file_put_contents($info_path, json_encode($user_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($info_path, "user_info.json");
    }
    
    /**
     * User-Info zu ZIP hinzufügen
     */
    private function addUserInfoToZip(\ZipArchive &$zip, string $user_folder, array $user_data): void
    {
        $temp_dir = $this->createTempDirectory('user_info');
        $info_content = $this->generateUserInfoContent($user_data);
        $info_path = $temp_dir . '/user_info.txt';
        
        file_put_contents($info_path, $info_content);
        $zip->addFile($info_path, $user_folder . "/user_info.txt");
    }
    
    /**
     * User-Submissions zu ZIP hinzufügen
     */
    private function addUserSubmissionsToZip(\ZipArchive &$zip, string $user_folder, \ilExAssignment $assignment, int $user_id): void
    {
        try {
            $submission = new \ilExSubmission($assignment, $user_id);
            if (!$submission || !$submission->hasSubmitted()) {
                return;
            }
            
            $submitted_files = $submission->getFiles();
            foreach ($submitted_files as $file) {
                if (isset($file['name']) && isset($file['full_path']) && file_exists($file['full_path'])) {
                    $safe_filename = $this->toAscii($file['name']);
                    $zip->addFile($file['full_path'], $user_folder . "/" . $safe_filename);
                }
            }
            
        } catch (Exception $e) {
            // Ignoriere fehlende Submissions
        }
    }
    
    /**
     * README erstellen (mit Übersetzungen)
     */
    private function addReadme(\ZipArchive &$zip, \ilExAssignment $assignment, array $users, string $temp_dir): void
    {
        $readme_content = $this->generateReadmeContent($assignment, $users);
        $readme_path = $temp_dir . '/README.md';
        
        file_put_contents($readme_path, $readme_content);
        $zip->addFile($readme_path, "README.md");
    }
    
    /**
     * Metadaten hinzufügen
     */
    private function addMetadata(\ZipArchive &$zip, \ilExAssignment $assignment, array $users, string $temp_dir): void
    {
        // User-Mapping
        $user_mapping = [
            'users' => []
        ];
        
        foreach ($users as $user_data) {
            $user_mapping['users'][$user_data['user_id']] = [
                'user_id' => $user_data['user_id'],
                'login' => $user_data['login'],
                'firstname' => $user_data['firstname'],
                'lastname' => $user_data['lastname'],
                'fullname' => $user_data['fullname'],
                'status' => $user_data['status'],
                'has_submission' => $user_data['has_submission']
            ];
        }
        
        $mapping_path = $temp_dir . '/user_mapping.json';
        file_put_contents($mapping_path, json_encode($user_mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($mapping_path, "user_mapping.json");
        
        // Statistiken
        $stats = $this->generateStatistics($assignment, $users);
        $stats_path = $temp_dir . '/statistics.json';
        file_put_contents($stats_path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($stats_path, "statistics.json");
    }
    
    /**
     * ZIP-Download senden
     */
    private function sendZIPDownload(string $zip_path, \ilExAssignment $assignment, array $users): void
    {
        if (!file_exists($zip_path)) {
            throw new Exception("ZIP file not found: $zip_path");
        }
        
        $filename = $this->generateDownloadFilename($assignment, $users);
        $filesize = filesize($zip_path);
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        readfile($zip_path);
        exit;
    }
    
    /**
     * Error-Response senden
     */
    private function sendErrorResponse(string $message): void
    {
        global $DIC;
        
        $tpl = $DIC->ui()->mainTemplate();
        $error_msg = $this->plugin->txt('error_multi_feedback_download') . ": " . $message;
        $tpl->setOnScreenMessage('failure', $error_msg, true);
        
        $ctrl = $DIC->ctrl();
        $ctrl->redirect(null, 'members');
    }
    
    /**
     * ZIP-Filename generieren
     */
    private function generateZIPFilename(\ilExAssignment $assignment, array $users): string
    {
        $base_name = $this->toAscii($assignment->getTitle());
        $user_count = count($users);
        $timestamp = date('Y-m-d_H-i-s');
        
        return "Multi_Feedback_Individual_{$base_name}_{$user_count}_Users_{$timestamp}.zip";
    }
    
    /**
     * Download-Filename generieren
     */
    private function generateDownloadFilename(\ilExAssignment $assignment, array $users): string
    {
        $base_name = $this->toAscii($assignment->getTitle());
        $user_count = count($users);
        
        return "Multi_Feedback_Individual_{$base_name}_{$user_count}_Users.zip";
    }
    
    /**
     * User-Folder-Name generieren
     */
    private function generateUserFolderName(array $user_data): string
    {
        return $this->toAscii(
            $user_data['lastname'] . "_" . 
            $user_data['firstname'] . "_" . 
            $user_data['login'] . "_" . 
            $user_data['user_id']
        );
    }
    
    /**
     * User-Info generieren
     */
    private function generateUserInfo(\ilExAssignment $assignment, array $users): array
    {
        return [
            'assignment' => [
                'id' => $assignment->getId(),
                'title' => $assignment->getTitle(),
                'type' => $assignment->getType()
            ],
            'multi_feedback' => [
                'user_count' => count($users),
                'user_ids' => array_column($users, 'user_id'),
                'generated_at' => date('Y-m-d H:i:s'),
                'plugin_version' => '1.1.0'
            ],
            'users' => $users
        ];
    }
    
    /**
     * User-Info-Content generieren (mit Übersetzungen)
     */
    private function generateUserInfoContent(array $user_data): string
    {
        $content = "USER INFORMATION\n";
        $content .= "=================\n\n";
        $content .= "User " . $this->plugin->txt('readme_id') . ": " . $user_data['user_id'] . "\n";
        $content .= $this->plugin->txt('readme_login') . ": " . $user_data['login'] . "\n";
        $content .= "Name: " . $user_data['fullname'] . "\n";
        $content .= $this->plugin->txt('readme_status') . ": " . $user_data['status'] . "\n";
        
        if (!empty($user_data['mark'])) {
            $content .= $this->plugin->txt('readme_note') . ": " . $user_data['mark'] . "\n";
        }
        
        if (!empty($user_data['comment'])) {
            $content .= "\nKommentar:\n" . $user_data['comment'] . "\n";
        }
        
        $content .= "\n" . $this->plugin->txt('readme_generated') . ": " . date('Y-m-d H:i:s') . "\n";
        
        return $content;
    }
    
    /**
     * README-Content generieren (mit Übersetzungen)
     */
    private function generateReadmeContent(\ilExAssignment $assignment, array $users): string
    {
        $user_count = count($users);
        $user_ids = implode(', ', array_column($users, 'user_id'));
        
        return "# " . $this->plugin->txt('readme_title') . " Individual - " . $assignment->getTitle() . "\n\n" .
               "## " . $this->plugin->txt('readme_information') . "\n\n" .
               "- **" . $this->plugin->txt('readme_assignment') . ":** " . $assignment->getTitle() . " (" . $this->plugin->txt('readme_id') . ": " . $assignment->getId() . ")\n" .
               "- **" . $this->plugin->txt('readme_users') . ":** $user_count " . $this->plugin->txt('readme_selected') . " (" . $this->plugin->txt('readme_id') . "s: $user_ids)\n" .
               "- **" . $this->plugin->txt('readme_generated') . ":** " . date('Y-m-d H:i:s') . "\n" .
               "- **" . $this->plugin->txt('readme_plugin') . ":** ExerciseStatusFile v1.1.0\n\n" .
               "## " . $this->plugin->txt('readme_structure') . "\n\n" .
               "```\n" .
               "Multi_Feedback_Individual_[Assignment]_[UserCount]_Users/\n" .
               "├── status.xlsx                # " . $this->plugin->txt('readme_structure_status_xlsx') . "\n" .
               "├── status.csv                 # " . $this->plugin->txt('readme_structure_status_csv') . "\n" .
               "├── user_info.json             # " . $this->plugin->txt('readme_structure_user_info_json') . "\n" .
               "├── user_mapping.json          # " . $this->plugin->txt('readme_structure_user_mapping') . "\n" .
               "├── statistics.json            # " . $this->plugin->txt('readme_structure_statistics') . "\n" .
               "├── README.md                  # " . $this->plugin->txt('readme_structure_readme') . "\n" .
               "└── [Lastname_Firstname_Login_ID]/  # " . $this->plugin->txt('readme_structure_per_user') . "\n" .
               "    ├── user_info.txt          # " . $this->plugin->txt('readme_structure_user_info_txt') . "\n" .
               "    └── [Submissions]          # " . $this->plugin->txt('readme_structure_submissions') . "\n" .
               "```\n\n" .
               "## " . $this->plugin->txt('readme_workflow') . "\n\n" .
               "1. **" . $this->plugin->txt('readme_workflow_step1') . ":** " . 
                   sprintf($this->plugin->txt('readme_workflow_step1_desc'), '`status.xlsx`', '`status.csv`') . "\n" .
               "2. **" . $this->plugin->txt('readme_workflow_step2') . ":** " . $this->plugin->txt('readme_workflow_step2_desc') . "\n" .
               "3. **" . $this->plugin->txt('readme_workflow_step3') . ":** " . $this->plugin->txt('readme_workflow_step3_desc') . "\n\n" .
               "## " . $this->plugin->txt('readme_user_overview') . "\n\n" .
               $this->generateUserOverviewForReadme($users) . "\n\n" .
               "---\n" .
               "*" . $this->plugin->txt('readme_generated_by') . "*\n";
    }
    
    /**
     * User-Overview für README (mit Übersetzungen)
     */
    private function generateUserOverviewForReadme(array $users): string
    {
        $overview = "";
        foreach ($users as $user_data) {
            $overview .= "### " . $user_data['fullname'] . " (" . $user_data['login'] . ")\n";
            $overview .= "- **" . $this->plugin->txt('readme_status') . ":** " . $user_data['status'] . "\n";
            
            if (!empty($user_data['mark'])) {
                $overview .= "- **" . $this->plugin->txt('readme_note') . ":** " . $user_data['mark'] . "\n";
            }
            
            $submission_text = $user_data['has_submission'] ? $this->plugin->txt('readme_yes') : $this->plugin->txt('readme_no');
            $overview .= "- **" . $this->plugin->txt('readme_submission') . ":** $submission_text\n";
            $overview .= "\n";
        }
        
        return $overview;
    }
    
    /**
     * Statistiken generieren
     */
    private function generateStatistics(\ilExAssignment $assignment, array $users): array
    {
        $stats = [
            'summary' => [
                'assignment_id' => $assignment->getId(),
                'assignment_title' => $assignment->getTitle(),
                'total_users' => count($users),
                'generated_at' => date('Y-m-d H:i:s')
            ],
            'status_distribution' => [],
            'users' => []
        ];
        
        $status_counts = [];
        foreach ($users as $user_data) {
            $status = $user_data['status'];
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
            
            $stats['users'][] = [
                'user_id' => $user_data['user_id'],
                'login' => $user_data['login'],
                'fullname' => $user_data['fullname'],
                'status' => $status,
                'has_submission' => $user_data['has_submission'] ?? false
            ];
        }
        
        $stats['status_distribution'] = $status_counts;
        
        return $stats;
    }
    
    /**
     * Temp-Directory erstellen
     */
    private function createTempDirectory(string $prefix): string
    {
        $temp_dir = sys_get_temp_dir() . '/plugin_individual_multi_feedback_' . $prefix . '_' . uniqid();
        mkdir($temp_dir, 0777, true);
        $this->temp_directories[] = $temp_dir;
        
        return $temp_dir;
    }
    
    /**
     * ASCII-Konvertierung
     */
    private function toAscii(string $filename): string
    {
        global $DIC;
        return (new \ilFileServicesPolicy($DIC->fileServiceSettings()))->ascii($filename);
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
}
?>
<?php
declare(strict_types=1);

/**
 * Multi-Feedback Download Handler
 * 
 * Verarbeitet Multi-Team-Downloads und generiert strukturierte ZIPs
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExMultiFeedbackDownloadHandler
{
    private ilLogger $logger;
    private array $temp_directories = [];
    private ilExTeamDataProvider $team_provider;
    private ?ilExerciseStatusFilePlugin $plugin = null;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->team_provider = new ilExTeamDataProvider();
        
        // Plugin-Instanz für Übersetzungen
        $plugin_id = 'exstatusfile';

        $repo = $DIC['component.repository'];
        $factory = $DIC['component.factory'];

        $info = $repo->getPluginById($plugin_id);
        if ($info !== null && $info->isActive()) {
            try {
                $this->plugin = $factory->getPlugin($plugin_id);
            } catch (Exception $e) {
                $this->plugin = null;
            }
        }
        
        register_shutdown_function([$this, 'cleanupAllTempDirectories']);
    }
    
    /**
     * Multi-Feedback-Download für ausgewählte Teams generieren
     */
    public function generateMultiFeedbackDownload(int $assignment_id, array $team_ids): void
    {
        try {
            $assignment = new \ilExAssignment($assignment_id);
            if (!$assignment->getAssignmentType()->usesTeams()) {
                throw new Exception("Assignment $assignment_id is not a team assignment");
            }
            
            $validated_teams = $this->validateTeams($assignment_id, $team_ids);
            if (empty($validated_teams)) {
                throw new Exception("No valid teams found");
            }
            
            $zip_path = $this->createMultiFeedbackZIP($assignment, $validated_teams);
            $this->sendZIPDownload($zip_path, $assignment, $validated_teams);
            
        } catch (Exception $e) {
            $this->logger->error("Multi-Feedback download error: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Teams validieren
     */
    private function validateTeams(int $assignment_id, array $team_ids): array
    {
        $validated_teams = [];
        $all_teams = $this->team_provider->getTeamsForAssignment($assignment_id);
        
        foreach ($team_ids as $team_id) {
            foreach ($all_teams as $team_data) {
                if ($team_data['team_id'] == $team_id) {
                    $validated_teams[] = $team_data;
                    break;
                }
            }
        }
        
        return $validated_teams;
    }
    
    /**
     * Multi-Feedback ZIP erstellen
     */
    private function createMultiFeedbackZIP(\ilExAssignment $assignment, array $teams): string
    {
        $temp_dir = $this->createTempDirectory('multi_feedback');
        $zip_filename = $this->generateZIPFilename($assignment, $teams);
        $zip_path = $temp_dir . '/' . $zip_filename;
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE) !== true) {
            throw new Exception("Could not create ZIP file: $zip_path");
        }
        
        try {
            $this->addStatusFiles($zip, $assignment, $teams, $temp_dir);
            $this->addTeamSubmissionsFromArrays($zip, $assignment, $teams);
            $this->addReadme($zip, $assignment, $teams, $temp_dir);
            $this->addMetadata($zip, $assignment, $teams, $temp_dir);
            
            $zip->close();
            return $zip_path;
            
        } catch (Exception $e) {
            $zip->close();
            throw $e;
        }
    }

    /**
     * Team-Submissions aus Array-Daten hinzufügen
     */
    private function addTeamSubmissionsFromArrays(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams_data): void
    {
        $base_name = $this->toAscii("Multi_Feedback_" . $assignment->getTitle() . "_" . $assignment->getId());
        
        foreach ($teams_data as $team_data) {
            $team_id = $team_data['team_id'];
            $team_folder = $base_name . "/Team_" . $team_id;
            
            $zip->addEmptyDir($team_folder);
            $this->addTeamInfoToZip($zip, $team_folder, $team_data);
            
            foreach ($team_data['members'] as $member_data) {
                $user_id = $member_data['user_id'];
                $user_folder = $team_folder . "/" . $this->generateUserFolderName($member_data);
                
                $zip->addEmptyDir($user_folder);
                $this->addUserSubmissionsToZip($zip, $user_folder, $assignment, $user_id);
            }
        }
    }    

    /**
     * Status-Files erstellen
     */
    private function addStatusFiles(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams, string $temp_dir): void
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
        
        // Team-Info
        $team_info = $this->generateTeamInfo($assignment, $teams);
        $info_path = $temp_dir . '/team_info.json';
        file_put_contents($info_path, json_encode($team_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($info_path, "team_info.json");
    }
    
    /**
     * Team-Info zu ZIP hinzufügen
     */
    private function addTeamInfoToZip(\ZipArchive &$zip, string $team_folder, array $team_data): void
    {
        $temp_dir = $this->createTempDirectory('team_info');
        $info_content = $this->generateTeamInfoContent($team_data);
        $info_path = $temp_dir . '/team_info.txt';
        
        file_put_contents($info_path, $info_content);
        $zip->addFile($info_path, $team_folder . "/team_info.txt");
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
    private function addReadme(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams, string $temp_dir): void
    {
        $readme_content = $this->generateReadmeContent($assignment, $teams);
        $readme_path = $temp_dir . '/README.md';
        
        file_put_contents($readme_path, $readme_content);
        $zip->addFile($readme_path, "README.md");
    }
    
    /**
     * Metadaten hinzufügen
     */
    private function addMetadata(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams, string $temp_dir): void
    {
        // Team-Mapping
        $team_mapping = [];
        foreach ($teams as $team_data) {
            $team_mapping['teams'][$team_data['team_id']] = [
                'team_id' => $team_data['team_id'],
                'member_count' => $team_data['member_count'],
                'members' => $team_data['members'],
                'status' => $team_data['status']
            ];
        }
        
        $mapping_path = $temp_dir . '/team_mapping.json';
        file_put_contents($mapping_path, json_encode($team_mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($mapping_path, "team_mapping.json");
        
        // Statistiken
        $stats = $this->generateStatistics($assignment, $teams);
        $stats_path = $temp_dir . '/statistics.json';
        file_put_contents($stats_path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($stats_path, "statistics.json");
    }
    
    /**
     * ZIP-Download senden
     */
    private function sendZIPDownload(string $zip_path, \ilExAssignment $assignment, array $teams): void
    {
        if (!file_exists($zip_path)) {
            throw new Exception("ZIP file not found: $zip_path");
        }
        
        $filename = $this->generateDownloadFilename($assignment, $teams);
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
    private function generateZIPFilename(\ilExAssignment $assignment, array $teams): string
    {
        $base_name = $this->toAscii($assignment->getTitle());
        $team_count = count($teams);
        $timestamp = date('Y-m-d_H-i-s');
        
        return "Multi_Feedback_{$base_name}_{$team_count}_Teams_{$timestamp}.zip";
    }
    
    /**
     * Download-Filename generieren
     */
    private function generateDownloadFilename(\ilExAssignment $assignment, array $teams): string
    {
        $base_name = $this->toAscii($assignment->getTitle());
        $team_count = count($teams);
        
        return "Multi_Feedback_{$base_name}_{$team_count}_Teams.zip";
    }
    
    /**
     * User-Folder-Name generieren
     */
    private function generateUserFolderName(array $member_data): string
    {
        return $this->toAscii(
            $member_data['lastname'] . "_" . 
            $member_data['firstname'] . "_" . 
            $member_data['login'] . "_" . 
            $member_data['user_id']
        );
    }
    
    /**
     * Team-Info generieren
     */
    private function generateTeamInfo(\ilExAssignment $assignment, array $teams): array
    {
        return [
            'assignment' => [
                'id' => $assignment->getId(),
                'title' => $assignment->getTitle(),
                'type' => $assignment->getType()
            ],
            'multi_feedback' => [
                'team_count' => count($teams),
                'team_ids' => array_column($teams, 'team_id'),
                'generated_at' => date('Y-m-d H:i:s'),
                'plugin_version' => '1.1.0'
            ],
            'teams' => $teams
        ];
    }
    
    /**
     * Team-Info-Content generieren (mit Übersetzungen)
     */
    private function generateTeamInfoContent(array $team_data): string
    {
        $content = "TEAM INFORMATION\n";
        $content .= "================\n\n";
        $content .= "Team " . $this->plugin->txt('readme_id') . ": " . $team_data['team_id'] . "\n";
        $content .= $this->plugin->txt('readme_members') . ": " . $team_data['member_count'] . "\n";
        $content .= $this->plugin->txt('readme_status') . ": " . $team_data['status'] . "\n";
        
        if (!empty($team_data['mark'])) {
            $content .= $this->plugin->txt('readme_note') . ": " . $team_data['mark'] . "\n";
        }
        
        $content .= "\n" . $this->plugin->txt('readme_members') . ":\n";
        foreach ($team_data['members'] as $member) {
            $content .= "- " . $member['fullname'] . " (" . $member['login'] . ")\n";
        }
        
        if (!empty($team_data['comment'])) {
            $content .= "\nKommentar:\n" . $team_data['comment'] . "\n";
        }
        
        $content .= "\n" . $this->plugin->txt('readme_generated') . ": " . date('Y-m-d H:i:s') . "\n";
        
        return $content;
    }
    
    /**
     * README-Content generieren (mit Übersetzungen)
     */
    private function generateReadmeContent(\ilExAssignment $assignment, array $teams): string
    {
        $team_count = count($teams);
        $team_ids = implode(', ', array_column($teams, 'team_id'));
        
        return "# " . $this->plugin->txt('readme_title') . " - " . $assignment->getTitle() . "\n\n" .
               "## " . $this->plugin->txt('readme_information') . "\n\n" .
               "- **" . $this->plugin->txt('readme_assignment') . ":** " . $assignment->getTitle() . " (" . $this->plugin->txt('readme_id') . ": " . $assignment->getId() . ")\n" .
               "- **" . $this->plugin->txt('readme_teams') . ":** $team_count " . $this->plugin->txt('readme_selected') . " (" . $this->plugin->txt('readme_id') . "s: $team_ids)\n" .
               "- **" . $this->plugin->txt('readme_generated') . ":** " . date('Y-m-d H:i:s') . "\n" .
               "- **" . $this->plugin->txt('readme_plugin') . ":** ExerciseStatusFile v1.1.0\n\n" .
               "## " . $this->plugin->txt('readme_structure') . "\n\n" .
               "```\n" .
               "Multi_Feedback_[Assignment]_[TeamCount]_Teams/\n" .
               "├── status.xlsx                # " . $this->plugin->txt('readme_structure_status_xlsx') . "\n" .
               "├── status.csv                 # " . $this->plugin->txt('readme_structure_status_csv') . "\n" .
               "├── team_info.json             # " . $this->plugin->txt('readme_structure_team_info_json') . "\n" .
               "├── team_mapping.json          # " . $this->plugin->txt('readme_structure_team_mapping') . "\n" .
               "├── statistics.json            # " . $this->plugin->txt('readme_structure_statistics') . "\n" .
               "├── README.md                  # " . $this->plugin->txt('readme_structure_readme') . "\n" .
               "└── Team_[ID]/                 # " . $this->plugin->txt('readme_structure_per_team') . "\n" .
               "    ├── team_info.txt          # " . $this->plugin->txt('readme_structure_team_info_txt') . "\n" .
               "    └── [Lastname_Firstname_Login_ID]/  # " . $this->plugin->txt('readme_structure_per_member') . "\n" .
               "        └── [Submissions]      # " . $this->plugin->txt('readme_structure_submissions') . "\n" .
               "```\n\n" .
               "## " . $this->plugin->txt('readme_workflow') . "\n\n" .
               "1. **" . $this->plugin->txt('readme_workflow_step1') . ":** " . 
                   sprintf($this->plugin->txt('readme_workflow_step1_desc'), '`status.xlsx`', '`status.csv`') . "\n" .
               "2. **" . $this->plugin->txt('readme_workflow_step2') . ":** " . $this->plugin->txt('readme_workflow_step2_desc') . "\n" .
               "3. **" . $this->plugin->txt('readme_workflow_step3') . ":** " . $this->plugin->txt('readme_workflow_step3_desc') . "\n\n" .
               "## " . $this->plugin->txt('readme_team_overview') . "\n\n" .
               $this->generateTeamOverviewForReadme($teams) . "\n\n" .
               "---\n" .
               "*" . $this->plugin->txt('readme_generated_by') . "*\n";
    }
    
    /**
     * Team-Overview für README (mit Übersetzungen)
     */
    private function generateTeamOverviewForReadme(array $teams): string
    {
        $overview = "";
        foreach ($teams as $team_data) {
            $overview .= "### Team " . $team_data['team_id'] . "\n";
            $overview .= "- **" . $this->plugin->txt('readme_status') . ":** " . $team_data['status'] . "\n";
            $overview .= "- **" . $this->plugin->txt('readme_members') . ":** ";
            
            $member_names = [];
            foreach ($team_data['members'] as $member) {
                $member_names[] = $member['fullname'] . " (" . $member['login'] . ")";
            }
            $overview .= implode(', ', $member_names) . "\n";
            
            if (!empty($team_data['mark'])) {
                $overview .= "- **" . $this->plugin->txt('readme_note') . ":** " . $team_data['mark'] . "\n";
            }
            
            $overview .= "\n";
        }
        
        return $overview;
    }
    
    /**
     * Statistiken generieren
     */
    private function generateStatistics(\ilExAssignment $assignment, array $teams): array
    {
        $stats = [
            'summary' => [
                'assignment_id' => $assignment->getId(),
                'assignment_title' => $assignment->getTitle(),
                'total_teams' => count($teams),
                'total_members' => array_sum(array_column($teams, 'member_count')),
                'generated_at' => date('Y-m-d H:i:s')
            ],
            'status_distribution' => [],
            'teams' => []
        ];
        
        $status_counts = [];
        foreach ($teams as $team_data) {
            $status = $team_data['status'];
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
            
            $stats['teams'][] = [
                'team_id' => $team_data['team_id'],
                'member_count' => $team_data['member_count'],
                'status' => $status,
                'has_submissions' => $team_data['has_submissions'] ?? false
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
        $temp_dir = sys_get_temp_dir() . '/plugin_multi_feedback_' . $prefix . '_' . uniqid();
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
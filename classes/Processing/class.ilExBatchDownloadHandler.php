<?php
declare(strict_types=1);

/**
 * Batch Download Handler - Phase 4
 * 
 * Verarbeitet Multi-Team-Downloads und generiert strukturierte ZIPs
 * Erweitert die normale Download-Funktionalität um Batch-Processing
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - Phase 4
 */
class ilExBatchDownloadHandler
{
    private ilLogger $logger;
    private array $temp_directories = [];
    private ilExTeamDataProvider $team_provider;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->team_provider = new ilExTeamDataProvider();
        
        // Cleanup bei Script-Ende registrieren
        register_shutdown_function([$this, 'cleanupAllTempDirectories']);
    }
    
    /**
     * MAIN: Batch-Download für ausgewählte Teams generieren
     */
    public function generateBatchDownload(int $assignment_id, array $team_ids): void
    {
        $this->logger->info("Plugin Batch: Starting batch download for assignment $assignment_id, teams: " . implode(',', $team_ids));
        
        try {
            // Assignment und Teams validieren
            $assignment = new \ilExAssignment($assignment_id);
            if (!$assignment->getAssignmentType()->usesTeams()) {
                throw new Exception("Assignment $assignment_id is not a team assignment");
            }
            
            $validated_teams = $this->validateTeams($assignment_id, $team_ids);
            if (empty($validated_teams)) {
                throw new Exception("No valid teams found for batch download");
            }
            
            // Batch-ZIP erstellen
            $zip_path = $this->createBatchZIP($assignment, $validated_teams);
            
            // Download senden
            $this->sendZIPDownload($zip_path, $assignment, $validated_teams);
            
        } catch (Exception $e) {
            $this->logger->error("Plugin Batch: Error in batch download: " . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Teams validieren und Daten laden
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
        
        $this->logger->info("Plugin Batch: Validated " . count($validated_teams) . " teams out of " . count($team_ids) . " requested");
        return $validated_teams;
    }
    
    /**
     * Batch-ZIP erstellen
     */
    private function createBatchZIP(\ilExAssignment $assignment, array $teams): string
    {
        $temp_dir = $this->createTempDirectory('batch_download');
        $zip_filename = $this->generateBatchZIPFilename($assignment, $teams);
        $zip_path = $temp_dir . '/' . $zip_filename;
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE) !== true) {
            throw new Exception("Could not create ZIP file: $zip_path");
        }
        
        try {
            // 1. Status-Files hinzufügen
            $this->addBatchStatusFiles($zip, $assignment, $teams, $temp_dir);
            
            // 2. Team-Struktur mit Submissions erstellen
            $this->addTeamSubmissionsFromArrays($zip, $assignment, $teams);
            
            // 3. Batch-README hinzufügen
            $this->addBatchReadme($zip, $assignment, $teams, $temp_dir);
            
            // 4. Team-Mapping und Metadaten
            $this->addBatchMetadata($zip, $assignment, $teams, $temp_dir);
            
            $zip->close();
            
            $this->logger->info("Plugin Batch: Created batch ZIP with " . $zip->numFiles . " files: $zip_path");
            return $zip_path;
            
        } catch (Exception $e) {
            $zip->close();
            throw $e;
        }
    }

    /**
     * Team-Submissions aus Array-Daten zu ZIP hinzufügen (statt Team-Objekten)
     */
    private function addTeamSubmissionsFromArrays(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams_data): void
    {
        $base_name = $this->toAscii("Batch_" . $assignment->getTitle() . "_" . $assignment->getId());
        
        foreach ($teams_data as $team_data) {
            $team_id = $team_data['team_id'];
            $team_folder = $base_name . "/Team_" . $team_id;
            
            // Team-Ordner erstellen
            $zip->addEmptyDir($team_folder);
            
            // Team-Info-File
            $this->addTeamInfoToZip($zip, $team_folder, $team_data);
            
            // Submissions für jedes Team-Mitglied (aus Array-Daten)
            foreach ($team_data['members'] as $member_data) {
                $user_id = $member_data['user_id'];
                $user_folder = $team_folder . "/" . $this->generateUserFolderName($member_data);
                
                $zip->addEmptyDir($user_folder);
                
                // User-Submissions hinzufügen
                $this->addUserSubmissionsToZip($zip, $user_folder, $assignment, $user_id);
            }
        }
        
        $this->logger->info("Plugin Batch: Added submissions for " . count($teams_data) . " teams from array data");
    }    

    /**
     * Batch-Status-Files erstellen
     */
    private function addBatchStatusFiles(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams, string $temp_dir): void
    {
        // Standard Status-Files für alle Teams
        $status_file = new ilPluginExAssignmentStatusFile();
        $status_file->init($assignment);
        
        // XLSX
        $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_XML);
        $xlsx_path = $temp_dir . '/batch_status.xlsx';
        $status_file->writeToFile($xlsx_path);
        
        if ($status_file->isWriteToFileSuccess() && file_exists($xlsx_path)) {
            $zip->addFile($xlsx_path, "batch_status.xlsx");
        }
        
        // CSV
        $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_CSV);
        $csv_path = $temp_dir . '/batch_status.csv';
        $status_file->writeToFile($csv_path);
        
        if ($status_file->isWriteToFileSuccess() && file_exists($csv_path)) {
            $zip->addFile($csv_path, "batch_status.csv");
        }
        
        // Team-spezifische Batch-Info
        $batch_info = $this->generateBatchInfo($assignment, $teams);
        $info_path = $temp_dir . '/batch_info.json';
        file_put_contents($info_path, json_encode($batch_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($info_path, "batch_info.json");
        
        $this->logger->info("Plugin Batch: Added batch status files");
    }
    
    /**
     * Team-Submissions zu ZIP hinzufügen
     */
    private function addTeamSubmissions(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams): void
    {
        $base_name = $this->toAscii("Batch_" . $assignment->getTitle() . "_" . $assignment->getId());
        
        foreach ($teams as $team_data) {
            $team_id = $team_data['team_id'];
            $team_folder = $base_name . "/Team_" . $team_id;
            
            // Team-Ordner erstellen
            $zip->addEmptyDir($team_folder);
            
            // Team-Info-File
            $this->addTeamInfoToZip($zip, $team_folder, $team_data);
            
            // Submissions für jedes Team-Mitglied
            foreach ($team_data['members'] as $member_data) {
                $user_id = $member_data['user_id'];
                $user_folder = $team_folder . "/" . $this->generateUserFolderName($member_data);
                
                $zip->addEmptyDir($user_folder);
                
                // User-Submissions hinzufügen
                $this->addUserSubmissionsToZip($zip, $user_folder, $assignment, $user_id);
            }
        }
        
        $this->logger->info("Plugin Batch: Added submissions for " . count($teams) . " teams");
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
            
            // Submission-Files holen
            $submitted_files = $submission->getFiles();
            foreach ($submitted_files as $file) {
                if (isset($file['name']) && isset($file['full_path']) && file_exists($file['full_path'])) {
                    $safe_filename = $this->toAscii($file['name']);
                    $zip->addFile($file['full_path'], $user_folder . "/" . $safe_filename);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Plugin Batch: Could not add submissions for user $user_id: " . $e->getMessage());
        }
    }
    
    /**
     * Batch-README erstellen
     */
    private function addBatchReadme(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams, string $temp_dir): void
    {
        $readme_content = $this->generateBatchReadmeContent($assignment, $teams);
        $readme_path = $temp_dir . '/README_BATCH.md';
        
        file_put_contents($readme_path, $readme_content);
        $zip->addFile($readme_path, "README_BATCH.md");
    }
    
    /**
     * Batch-Metadaten hinzufügen
     */
    private function addBatchMetadata(\ZipArchive &$zip, \ilExAssignment $assignment, array $teams, string $temp_dir): void
    {
        // Team-Mapping für Import/Export
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
        
        // Batch-Statistics
        $stats = $this->generateBatchStatistics($assignment, $teams);
        $stats_path = $temp_dir . '/batch_statistics.json';
        file_put_contents($stats_path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFile($stats_path, "batch_statistics.json");
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
        
        // HTTP-Headers für Download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // ZIP-Inhalt senden
        readfile($zip_path);
        
        $this->logger->info("Plugin Batch: Download sent - file: $filename, size: $filesize bytes");
        exit;
    }
    
    /**
     * Error-Response senden
     */
    private function sendErrorResponse(string $message): void
    {
        global $DIC;
        
        $tpl = $DIC->ui()->mainTemplate();
        $tpl->setOnScreenMessage('failure', "Fehler beim Batch-Download: " . $message, true);
        
        // Redirect zurück zur Members-Seite
        $ctrl = $DIC->ctrl();
        $ctrl->redirect(null, 'members');
    }
    
    /**
     * UTILITY: Batch-ZIP-Filename generieren
     */
    private function generateBatchZIPFilename(\ilExAssignment $assignment, array $teams): string
    {
        $base_name = $this->toAscii($assignment->getTitle());
        $team_count = count($teams);
        $timestamp = date('Y-m-d_H-i-s');
        
        return "Batch_{$base_name}_{$team_count}_Teams_{$timestamp}.zip";
    }
    
    /**
     * UTILITY: Download-Filename generieren
     */
    private function generateDownloadFilename(\ilExAssignment $assignment, array $teams): string
    {
        $base_name = $this->toAscii($assignment->getTitle());
        $team_count = count($teams);
        
        return "Batch_Feedback_{$base_name}_{$team_count}_Teams.zip";
    }
    
    /**
     * UTILITY: User-Folder-Name generieren
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
     * UTILITY: Batch-Info generieren
     */
    private function generateBatchInfo(\ilExAssignment $assignment, array $teams): array
    {
        return [
            'assignment' => [
                'id' => $assignment->getId(),
                'title' => $assignment->getTitle(),
                'type' => $assignment->getType()
            ],
            'batch' => [
                'team_count' => count($teams),
                'team_ids' => array_column($teams, 'team_id'),
                'generated_at' => date('Y-m-d H:i:s'),
                'plugin_version' => '1.1.0'
            ],
            'teams' => $teams
        ];
    }
    
    /**
     * UTILITY: Team-Info-Content generieren
     */
    private function generateTeamInfoContent(array $team_data): string
    {
        $content = "TEAM INFORMATION\n";
        $content .= "================\n\n";
        $content .= "Team ID: " . $team_data['team_id'] . "\n";
        $content .= "Mitglieder: " . $team_data['member_count'] . "\n";
        $content .= "Status: " . $team_data['status'] . "\n";
        
        if (!empty($team_data['mark'])) {
            $content .= "Note: " . $team_data['mark'] . "\n";
        }
        
        $content .= "\nTeam-Mitglieder:\n";
        foreach ($team_data['members'] as $member) {
            $content .= "- " . $member['fullname'] . " (" . $member['login'] . ")\n";
        }
        
        if (!empty($team_data['comment'])) {
            $content .= "\nKommentar:\n" . $team_data['comment'] . "\n";
        }
        
        $content .= "\nGeneriert: " . date('Y-m-d H:i:s') . "\n";
        
        return $content;
    }
    
    /**
     * UTILITY: Batch-README-Content generieren
     */
    private function generateBatchReadmeContent(\ilExAssignment $assignment, array $teams): string
    {
        $team_count = count($teams);
        $team_ids = implode(', ', array_column($teams, 'team_id'));
        
        return "# Batch Multi-Feedback - " . $assignment->getTitle() . "\n\n" .
               "## Batch-Informationen\n\n" .
               "- **Assignment:** " . $assignment->getTitle() . " (ID: " . $assignment->getId() . ")\n" .
               "- **Teams:** $team_count ausgewählt (IDs: $team_ids)\n" .
               "- **Generiert:** " . date('Y-m-d H:i:s') . "\n" .
               "- **Plugin:** ExerciseStatusFile v1.1.0 - Phase 4\n\n" .
               "## Struktur\n\n" .
               "```\n" .
               "Batch_[Assignment]_[TeamCount]_Teams/\n" .
               "├── batch_status.xlsx          # Status-File für alle Teams (Excel)\n" .
               "├── batch_status.csv           # Status-File für alle Teams (CSV)\n" .
               "├── batch_info.json            # Batch-Metadaten\n" .
               "├── team_mapping.json          # Team-Mapping für Import\n" .
               "├── batch_statistics.json      # Statistiken\n" .
               "├── README_BATCH.md            # Diese Datei\n" .
               "└── Team_[ID]/                 # Pro Team\n" .
               "    ├── team_info.txt          # Team-Informationen\n" .
               "    └── [Lastname_Firstname_Login_ID]/  # Pro Team-Mitglied\n" .
               "        └── [Submissions]      # Abgabe-Files\n" .
               "```\n\n" .
               "## Workflow\n\n" .
               "1. **Status bearbeiten:** Öffne `batch_status.xlsx` oder `batch_status.csv`\n" .
               "2. **Feedback hinzufügen:** Lege Feedback-Files in die entsprechenden User-Ordner\n" .
               "3. **Re-Upload:** ZIP komplett wieder hochladen für automatische Verarbeitung\n\n" .
               "## Team-Übersicht\n\n" .
               $this->generateTeamOverviewForReadme($teams) . "\n\n" .
               "---\n" .
               "*Generiert durch ExerciseStatusFile Plugin - Phase 4 Multi-Feedback*\n";
    }
    
    /**
     * UTILITY: Team-Overview für README
     */
    private function generateTeamOverviewForReadme(array $teams): string
    {
        $overview = "";
        foreach ($teams as $team_data) {
            $overview .= "### Team " . $team_data['team_id'] . "\n";
            $overview .= "- **Status:** " . $team_data['status'] . "\n";
            $overview .= "- **Mitglieder:** ";
            
            $member_names = [];
            foreach ($team_data['members'] as $member) {
                $member_names[] = $member['fullname'] . " (" . $member['login'] . ")";
            }
            $overview .= implode(', ', $member_names) . "\n";
            
            if (!empty($team_data['mark'])) {
                $overview .= "- **Note:** " . $team_data['mark'] . "\n";
            }
            
            $overview .= "\n";
        }
        
        return $overview;
    }
    
    /**
     * UTILITY: Batch-Statistiken generieren
     */
    private function generateBatchStatistics(\ilExAssignment $assignment, array $teams): array
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
        
        // Status-Verteilung berechnen
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
     * UTILITY: Temp-Directory erstellen
     */
    private function createTempDirectory(string $prefix): string
    {
        $temp_dir = sys_get_temp_dir() . '/plugin_batch_' . $prefix . '_' . uniqid();
        mkdir($temp_dir, 0777, true);
        $this->temp_directories[] = $temp_dir;
        
        return $temp_dir;
    }
    
    /**
     * UTILITY: ASCII-Konvertierung
     */
    private function toAscii(string $filename): string
    {
        global $DIC;
        return (new \ilFileServicesPolicy($DIC->fileServiceSettings()))->ascii($filename);
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
            $this->logger->warning("Plugin Batch: Could not cleanup temp directory $temp_dir: " . $e->getMessage());
        }
    }
}
<?php
declare(strict_types=1);

class ilExerciseStatusFileUIHookGUI extends ilUIHookPluginGUI
{
    protected ilExerciseStatusFilePlugin $plugin;
    
    // Klassen-Variable für Assignment ID zwischen Aufrufen
    protected static $cached_assignment_id = null;

    public function __construct(ilExerciseStatusFilePlugin $plugin) 
    {
        $this->plugin = $plugin;
    }

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        // Komplett leer - wir nutzen nur modifyGUI()
        // ABER: Speichere Assignment ID für modifyGUI()
        if (self::$cached_assignment_id === null) {
            self::$cached_assignment_id = $this->detectAssignmentId();
        }
        
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

    public function modifyGUI(string $a_comp, string $a_part, array $a_par = []): void
    {
        try {
            global $DIC;
            
            // Direkt Template holen - ohne komplexe Prüfungen
            $tpl = $DIC->ui()->mainTemplate();
            $ctrl = $DIC->ctrl();
            $class = strtolower($ctrl->getCmdClass());
            $cmd = $ctrl->getCmd();
            
            // NUR in Exercise Management -> Members
            if ($class !== 'ilexercisemanagementgui' || $cmd !== 'members') {
                return;
            }
            
            $logger = $DIC->logger()->root();
            
            // Assignment ID: Erst Cache, dann detectieren
            $ass_id = self::$cached_assignment_id ?? $this->detectAssignmentId();
            
            // Fallback: Hart-kodiert für jetzt (da wir wissen es ist 36)
            if (!$ass_id && isset($_GET['ass_id'])) {
                $ass_id = (int)$_GET['ass_id'];
            }
            
            $logger->info("Plugin modifyGUI: $class->$cmd (ass:$ass_id, cached:" . (self::$cached_assignment_id ?? 'null') . ")");
            
            if ($ass_id) {
                $db_info = $this->getAssignmentInfo($ass_id);
                
                // Prüfe ob es ein Team-Assignment ist
                if (strpos($db_info, '✅ IS TEAM') !== false) {
                    // TEAM BUTTON HINZUFÜGEN!
                    $tpl->addOnLoadCode('
                        console.log("ADDING TEAM BUTTON for assignment ' . $ass_id . '");
                        setTimeout(function() {
                            // Entferne vorherige Plugin-Box
                            var existingBox = document.getElementById("plugin_team_button");
                            if (existingBox) existingBox.remove();
                            
                            // Erstelle Team-Button Box
                            var buttonBox = document.createElement("div");
                            buttonBox.id = "plugin_team_button";
                            buttonBox.innerHTML = `
                                <div style="background: #28a745; color: white; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
                                    <strong>📁 TEAM MULTI-FEEDBACK</strong><br>
                                    <small>Assignment: ' . $ass_id . ' (Team-Assignment erkannt)</small><br>
                                    <button onclick="alert(\'Team Multi-Feedback würde hier starten!\')" 
                                            style="margin-top: 5px; padding: 8px 15px; background: white; color: #28a745; border: 1px solid #28a745; border-radius: 3px; cursor: pointer;">
                                        🚀 Multi-Feedback starten
                                    </button>
                                </div>
                            `;
                            
                            // Füge Button vor der Tabelle ein
                            var table = document.querySelector("form[name=\'ilExcIDlForm\']");
                            if (table && table.parentNode) {
                                table.parentNode.insertBefore(buttonBox, table);
                                console.log("Team button added before table");
                            } else {
                                // Fallback: Nach Toolbar einfügen
                                var toolbar = document.querySelector(".ilToolbarContainer");
                                if (toolbar && toolbar.parentNode) {
                                    toolbar.parentNode.insertBefore(buttonBox, toolbar.nextSibling);
                                    console.log("Team button added after toolbar");
                                } else {
                                    // Letzter Fallback: In Content Container
                                    var content = document.querySelector("#il_center_col");
                                    if (content) {
                                        content.insertBefore(buttonBox, content.firstChild);
                                        console.log("Team button added to content");
                                    }
                                }
                            }
                            
                        }, 500);
                    ');
                } else {
                    // INFO für Nicht-Team-Assignments
                    $tpl->addOnLoadCode('
                        console.log("Assignment ' . $ass_id . ' is not a team assignment");
                        setTimeout(function() {
                            var existingBox = document.getElementById("plugin_team_button");
                            if (existingBox) existingBox.remove();
                            
                            var infoBox = document.createElement("div");
                            infoBox.id = "plugin_team_button";
                            infoBox.innerHTML = `
                                <div style="background: #ffc107; color: black; padding: 8px; margin-bottom: 10px; border-radius: 5px;">
                                    <small>ℹ️ Assignment ' . $ass_id . ' ist kein Team-Assignment. Multi-Feedback nur für Teams verfügbar.</small>
                                </div>
                            `;
                            
                            var table = document.querySelector("form[name=\'ilExcIDlForm\']");
                            if (table && table.parentNode) {
                                table.parentNode.insertBefore(infoBox, table);
                            } else {
                                var toolbar = document.querySelector(".ilToolbarContainer");
                                if (toolbar && toolbar.parentNode) {
                                    toolbar.parentNode.insertBefore(infoBox, toolbar.nextSibling);
                                }
                            }
                        }, 500);
                    ');
                }
            } else {
                // Debug: Zeige dass Plugin läuft, aber keine Assignment ID
                $tpl->addOnLoadCode('
                    console.log("PLUGIN DEBUG: No assignment ID found");
                    setTimeout(function() {
                        var existingBox = document.getElementById("plugin_team_button");
                        if (existingBox) existingBox.remove();
                        
                        var debugBox = document.createElement("div");
                        debugBox.id = "plugin_team_button";
                        debugBox.innerHTML = "🔧 PLUGIN WORKS! No Assignment ID found.";
                        debugBox.style.cssText = "position: fixed; top: 10px; right: 10px; background: blue; color: white; padding: 10px; z-index: 9999; font-size: 12px;";
                        document.body.appendChild(debugBox);
                        
                        setTimeout(function() { debugBox.remove(); }, 3000);
                    }, 200);
                ');
            }
            
        } catch (Exception $e) {
            // Ignoriere Template-Fehler
            return;
        }
    }

    protected function detectAssignmentId(): ?int
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        // 1. GET Parameter
        if (isset($_GET['ass_id']) && is_numeric($_GET['ass_id'])) {
            $logger->info("Plugin: Assignment ID found in GET: " . $_GET['ass_id']);
            return (int)$_GET['ass_id'];
        }
        
        // 2. HTTP Request
        $query_params = $DIC->http()->request()->getQueryParams();
        if (isset($query_params['ass_id']) && is_numeric($query_params['ass_id'])) {
            $logger->info("Plugin: Assignment ID found in HTTP request: " . $query_params['ass_id']);
            return (int)$query_params['ass_id'];
        }
        
        // 3. POST Parameter (bei Form-Submissions)
        $post_params = $DIC->http()->request()->getParsedBody();
        if (isset($post_params['ass_id']) && is_numeric($post_params['ass_id'])) {
            $logger->info("Plugin: Assignment ID found in POST: " . $post_params['ass_id']);
            return (int)$post_params['ass_id'];
        }
        
        // 4. Session-basierte Suche
        $session_ass_id = $this->getAssignmentFromSession();
        if ($session_ass_id) {
            $logger->info("Plugin: Assignment ID found in session: " . $session_ass_id);
            return $session_ass_id;
        }
        
        // 5. Über ref_id versuchen - aber erweitert
        $ref_id = $_GET['ref_id'] ?? $query_params['ref_id'] ?? null;
        if ($ref_id && is_numeric($ref_id)) {
            $logger->info("Plugin: Trying to get assignment from ref_id: " . $ref_id);
            return $this->tryGetAssignmentFromContext((int)$ref_id);
        }
        
        $logger->warning("Plugin: No assignment ID found");
        return null;
    }

    protected function getAssignmentFromSession(): ?int
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            // ILIAS speichert oft aktuelle Assignments in der Session
            $session_data = $_SESSION ?? [];
            
            // Verschiedene Session-Keys die ILIAS verwendet
            $possible_keys = [
                'exc_assignment',
                'current_assignment',
                'selected_assignment',
                'ass_id',
                'exercise_assignment'
            ];
            
            foreach ($possible_keys as $key) {
                if (isset($session_data[$key]) && is_numeric($session_data[$key])) {
                    $logger->info("Plugin: Found assignment in session key '$key': " . $session_data[$key]);
                    return (int)$session_data[$key];
                }
            }
            
            // Auch in verschachtelten Session-Arrays suchen
            foreach ($session_data as $main_key => $value) {
                if (is_array($value) && strpos($main_key, 'exc') !== false) {
                    foreach ($value as $sub_key => $sub_value) {
                        if (($sub_key === 'ass_id' || $sub_key === 'assignment_id') && is_numeric($sub_value)) {
                            $logger->info("Plugin: Found assignment in session '$main_key.$sub_key': " . $sub_value);
                            return (int)$sub_value;
                        }
                    }
                }
            }
            
            $logger->info("Plugin: No assignment found in session");
            return null;
            
        } catch (Exception $e) {
            $logger->error("Plugin: Error searching session: " . $e->getMessage());
            return null;
        }
    }

    protected function tryGetAssignmentFromContext(int $ref_id): ?int
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            // Exercise Objekt laden
            $obj = \ilObjectFactory::getInstanceByRefId($ref_id);
            if (!$obj || !($obj instanceof \ilObjExercise)) {
                $logger->info("Plugin: ref_id $ref_id is not an Exercise object");
                return null;
            }
            
            // Alle Assignments des Exercises holen
            $assignments = \ilExAssignment::getInstancesByExercise($obj->getId());
            $logger->info("Plugin: Exercise has " . count($assignments) . " assignments");
            
            // Wenn nur ein Assignment existiert, das nehmen
            if (count($assignments) === 1) {
                $assignment_id = array_values($assignments)[0]->getId();
                $logger->info("Plugin: Found single assignment ID $assignment_id for exercise " . $obj->getId());
                return $assignment_id;
            }
            
            // Mehrere Assignments: Versuche das Team-Assignment zu finden
            foreach ($assignments as $assignment) {
                $assignment_type = $assignment->getType();
                $logger->info("Plugin: Assignment " . $assignment->getId() . " has type: $assignment_type");
                
                // Type 4 = Team Assignment
                if ($assignment_type == 4) {
                    $logger->info("Plugin: Found team assignment ID " . $assignment->getId());
                    return $assignment->getId();
                }
            }
            
            // Fallback: Erstes Assignment nehmen und loggen
            if (!empty($assignments)) {
                $first_assignment = array_values($assignments)[0];
                $logger->warning("Plugin: Multiple assignments found, taking first one: " . $first_assignment->getId());
                return $first_assignment->getId();
            }
            
            $logger->warning("Plugin: No assignments found in exercise");
            return null;
            
        } catch (Exception $e) {
            $logger->error("Plugin: Error getting assignment from context: " . $e->getMessage());
            return null;
        }
    }

    protected function getAssignmentInfo(int $ass_id): string
    {
        try {
            global $DIC;
            $logger = $DIC->logger()->root();
            $db = $DIC->database();
            $query = "SELECT exc_id, type FROM exc_assignment WHERE id = " . $db->quote($ass_id, 'integer');
            $result = $db->query($query);
            
            if ($result->numRows() > 0) {
                $row = $db->fetchAssoc($result);
                $exc_id = $row['exc_id'];
                $type = $row['type'];
                
                // Team-Type prüfen (4 statt 6!)
                $is_team_assignment = ($type == 4);
                $team_status = $is_team_assignment ? "✅ IS TEAM" : "❌ NOT TEAM";
                
                $info = "DB OK: exc_id=$exc_id, type=$type ($team_status)";
                $logger->info("Plugin: Assignment info for $ass_id: $info");
                return $info;
            } else {
                $logger->warning("Plugin: Assignment $ass_id not found in database");
                return "DB: Assignment not found";
            }
        } catch (Exception $e) {
            $logger->error("Plugin: Database error getting assignment info: " . $e->getMessage());
            return "DB Error: " . $e->getMessage();
        }
    }

    // Alle anderen Methoden bleiben unverändert - nur für ZIP-Verarbeitung
    
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
            
            // Prüfe ob Teams verwendet werden
            if ($assignment->getAssignmentType()->usesTeams()) {
                $this->addStatusFilesToZipForTeams($zip, $assignment, $members);
                $this->createTeamStructureInZip($zip, $assignment, $members);
            } else {
                $this->addStatusFilesToZip($zip, $assignment, $members);
            }
            
            $logger->info("Plugin StatusFile: After adding status files - ZIP has " . $zip->numFiles . " files");
            
        } catch (Exception $e) {
            $logger->error("Plugin StatusFile: Error adding status files to ZIP: " . $e->getMessage());
        }
    }

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
            $assignment = new \ilExAssignment($assignment_id);
            $zip_content = null;
            
            if (isset($a_par['zip_content']) && is_string($a_par['zip_content'])) {
                $zip_content = $a_par['zip_content'];
            } elseif (isset($a_par['upload_result'])) {
                $zip_content = $this->getZipContentFromUploadResult($a_par['upload_result']);
            }
            
            if ($zip_content && $this->isZipContent($zip_content)) {
                if ($assignment->getAssignmentType()->usesTeams()) {
                    $this->processTeamZipContent($zip_content, $assignment_id, $tutor_id);
                } else {
                    $this->processZipContent($zip_content, $assignment_id, $tutor_id);
                }
                
                $_SESSION['exc_status_files_processed'][$assignment_id][$tutor_id] = time();
                $logger->info("Status files processed successfully");
            }
            
        } catch (Exception $e) {
            $logger->error("Error in feedback processing: " . $e->getMessage());
        }
    }

    protected function addStatusFilesToZipForTeams(\ZipArchive &$zip, \ilExAssignment $assignment, array $members): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_team_status_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        try {
            // XLSX Status File
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_XML);
            $xlsx_path = $tmp_dir . '/status.xlsx';
            $status_file->writeToFile($xlsx_path);
            
            if ($status_file->isWriteToFileSuccess() && file_exists($xlsx_path)) {
                $zip->addFile($xlsx_path, "status.xlsx");
                $logger->info("Added team status.xlsx");
            }
            
            // CSV Status File
            $csv_status_file = new ilPluginExAssignmentStatusFile();
            $csv_status_file->init($assignment);
            $csv_status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_CSV);
            $csv_path = $tmp_dir . '/status.csv';
            $csv_status_file->writeToFile($csv_path);
            
            if ($csv_status_file->isWriteToFileSuccess() && file_exists($csv_path)) {
                $zip->addFile($csv_path, "status.csv");
                $logger->info("Added team status.csv");
            }
            
        } catch (Exception $e) {
            $logger->error("Error creating team status files: " . $e->getMessage());
            $this->cleanupTempDir($tmp_dir);
            throw $e;
        }
        
        $this->registerShutdownCleanup($tmp_dir);
    }

    protected function createTeamStructureInZip(\ZipArchive &$zip, \ilExAssignment $assignment, array $members): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            $base_name = trim(str_replace(" ", "_", $assignment->getTitle() . "_" . $assignment->getId()));
            $base_name = "multi_feedback_" . $this->toAscii($base_name);
            
            $teams = ilExAssignmentTeam::getInstancesFromMap($assignment->getId());
            $logger->info("Creating team structure for " . count($teams) . " teams");
            
            foreach ($teams as $team_id => $team) {
                $team_folder_name = "Team " . $team_id;
                $team_path = $base_name . "/" . $team_folder_name;
                
                $zip->addEmptyDir($team_path);
                
                foreach ($team->getMembers() as $user_id) {
                    $user_data = \ilObjUser::_lookupName($user_id);
                    if ($user_data && $user_data['login']) {
                        $subdir = $user_data["lastname"] . "_" . 
                                 $user_data["firstname"] . "_" . 
                                 $user_data["login"] . "_" . 
                                 $user_id;
                        $subdir = $this->toAscii($subdir);
                        
                        $user_path = $team_path . "/" . $subdir;
                        $zip->addEmptyDir($user_path);
                    }
                }
            }
            
        } catch (Exception $e) {
            $logger->error("Error creating team structure: " . $e->getMessage());
        }
    }

    protected function processTeamZipContent(string $zip_content, int $assignment_id, int $tutor_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            $temp_zip = sys_get_temp_dir() . '/plugin_team_feedback_' . uniqid() . '.zip';
            file_put_contents($temp_zip, $zip_content);
            
            if (file_exists($temp_zip) && filesize($temp_zip) > 100) {
                $this->processTeamStatusFilesFromZip($temp_zip, $assignment_id, $tutor_id);
            }
            
            if (file_exists($temp_zip)) {
                unlink($temp_zip);
            }
            
        } catch (Exception $e) {
            $logger->error("Error processing team ZIP: " . $e->getMessage());
        }
    }

    protected function processTeamStatusFilesFromZip(string $zip_path, int $assignment_id, int $tutor_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return;
        }
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_team_extract_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        try {
            $status_files_found = [];
            
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
            
            if (!empty($status_files_found)) {
                $this->processExtractedStatusFiles($status_files_found, $assignment_id);
            }
            
        } finally {
            $this->cleanupTempDir($tmp_dir);
        }
    }

    protected function addStatusFilesToZip(\ZipArchive &$zip, \ilExAssignment $assignment, array $members): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_status_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        try {
            // XLSX Status File
            $status_file = new ilPluginExAssignmentStatusFile();
            $status_file->init($assignment);
            $status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_XML);
            $xlsx_path = $tmp_dir . '/status.xlsx';
            $status_file->writeToFile($xlsx_path);
            
            if ($status_file->isWriteToFileSuccess() && file_exists($xlsx_path)) {
                $zip->addFile($xlsx_path, "status.xlsx");
                $logger->info("Added status.xlsx");
            }
            
            // CSV Status File
            $csv_status_file = new ilPluginExAssignmentStatusFile();
            $csv_status_file->init($assignment);
            $csv_status_file->setFormat(ilPluginExAssignmentStatusFile::FORMAT_CSV);
            $csv_path = $tmp_dir . '/status.csv';
            $csv_status_file->writeToFile($csv_path);
            
            if ($csv_status_file->isWriteToFileSuccess() && file_exists($csv_path)) {
                $zip->addFile($csv_path, "status.csv");
                $logger->info("Added status.csv");
            }
            
        } catch (Exception $e) {
            $logger->error("Error creating status files: " . $e->getMessage());
            $this->cleanupTempDir($tmp_dir);
            throw $e;
        }
        
        $this->registerShutdownCleanup($tmp_dir);
    }

    protected function processZipContent(string $zip_content, int $assignment_id, int $tutor_id): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        try {
            $temp_zip = sys_get_temp_dir() . '/plugin_feedback_' . uniqid() . '.zip';
            file_put_contents($temp_zip, $zip_content);
            
            if (file_exists($temp_zip) && filesize($temp_zip) > 100) {
                $this->processStatusFilesFromZip($temp_zip, $assignment_id, $tutor_id);
            }
            
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
            return;
        }
        
        $tmp_dir = sys_get_temp_dir() . '/plugin_extract_' . uniqid();
        mkdir($tmp_dir, 0777, true);
        
        try {
            $status_files_found = [];
            
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
            
            if (!empty($status_files_found)) {
                $this->processExtractedStatusFiles($status_files_found, $assignment_id);
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
        
        foreach ($status_files as $file_path) {
            if (!file_exists($file_path)) {
                continue;
            }
            
            try {
                $status_file->loadFromFile($file_path);
                
                if ($status_file->isLoadFromFileSuccess() && $status_file->hasUpdates()) {
                    $status_file->applyStatusUpdates();
                    $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $status_file->getInfo(), true);
                    break;
                }
            } catch (Exception $e) {
                $logger->error("Error processing status file: " . $e->getMessage());
            }
        }
    }

    protected function getZipContentFromUploadResult($upload_result): ?string
    {
        if (method_exists($upload_result, 'getPath') && $upload_result->getPath()) {
            $temp_path = $upload_result->getPath();
            if (file_exists($temp_path)) {
                return file_get_contents($temp_path);
            }
        }
        return null;
    }

    protected function isZipContent(string $content): bool
    {
        return strlen($content) > 100 && substr($content, 0, 2) === 'PK';
    }

    protected function toAscii(string $filename): string
    {
        global $DIC;
        return (new \ilFileServicesPolicy($DIC->fileServiceSettings()))->ascii($filename);
    }

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
}
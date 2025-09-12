<?php
declare(strict_types=1);

// === INCLUDES FÜR REFACTORED KLASSEN + PHASE 4 ===
// Detection Classes
require_once __DIR__ . '/Detection/class.ilExAssignmentDetector.php';

// UI Classes  
require_once __DIR__ . '/UI/class.ilExTeamButtonRenderer.php';

// Processing Classes
require_once __DIR__ . '/Processing/class.ilExFeedbackDownloadHandler.php';
require_once __DIR__ . '/Processing/class.ilExFeedbackUploadHandler.php';

// PHASE 4: Team Multi-Feedback Classes
require_once __DIR__ . '/Processing/class.ilExTeamDataProvider.php';
require_once __DIR__ . '/Processing/class.ilExBatchDownloadHandler.php';

/**
 * Exercise Status File UI Hook - PHASE 4 COMPLETE mit Upload/Download
 * 
 * Hauptklasse für UI-Integration mit vollständigem Team Multi-Feedback
 * Features: Robuste Detection, Refactored Architecture, Team Multi-Feedback, Upload/Download
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - Phase 4 Complete with Upload
 */
class ilExerciseStatusFileUIHookGUI extends ilUIHookPluginGUI
{
    protected ilExerciseStatusFilePlugin $plugin;
    protected ilLogger $logger;

    public function __construct(ilExerciseStatusFilePlugin $plugin) 
    {
        $this->plugin = $plugin;
        
        global $DIC;
        $this->logger = $DIC->logger()->root();
    }

    /**
     * PHASE 4: Erweiterte getHTML() mit Batch-Download-Support
     */
    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        $return = ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];

        // PHASE 4: Batch-Download FRÜH abfangen (vor Controller)
        if (isset($_POST['plugin_action']) && $_POST['plugin_action'] === 'batch_download') {
            $this->handleBatchDownloadRequest();
            // Script wird hier beendet durch den Download
            return $return;
        }

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

    /**
     * PHASE 4: MAIN UI MODIFICATION mit AJAX-Support und Upload
     */
    public function modifyGUI(string $a_comp, string $a_part, array $a_par = []): void
    {
        
        try {
            global $DIC;
            
            // PHASE 4: AJAX-Requests abfangen
            if ($this->handleAJAXRequests()) {
                return; // Request wurde verarbeitet und beendet
            }
            
            $tpl = $DIC->ui()->mainTemplate();
            $ctrl = $DIC->ctrl();
            $class = strtolower($ctrl->getCmdClass());
            $cmd = $ctrl->getCmd();
            
            // NUR in Exercise Management -> Members
            if ($class !== 'ilexercisemanagementgui' || $cmd !== 'members') {
                return;
            }
            
            $this->logger->info("Plugin UI: In target context - detecting assignment (Phase 4)");
            
            // Assignment Detection
            $detector = new ilExAssignmentDetector();
            $assignment_id = $detector->detectAssignmentId();
            
            $this->logger->info("Plugin UI: Assignment ID detected: " . ($assignment_id ?? 'none'));
            
            // UI-Rendering
            $this->renderUI($assignment_id);
            
        } catch (Exception $e) {
            $this->logger->error("Plugin UI: Error in modifyGUI: " . $e->getMessage());
        }
    }
    
    /**
     * PHASE 4: AJAX-Requests verarbeiten (GET und POST)
     */
    private function handleAJAXRequests(): bool
    {
        // Debug-Ausgabe
        $this->logger->info("Plugin UI: Checking AJAX request - plugin_action: " . 
                           ($_GET['plugin_action'] ?? $_POST['plugin_action'] ?? 'none'));
        
        // Prüfe AJAX-Requests
        $is_ajax_get = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                       $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
                       $_SERVER['REQUEST_METHOD'] === 'GET';
        
        $is_ajax_post = isset($_POST['plugin_action']); // POST-Uploads sind immer für uns
        
        if (!$is_ajax_get && !$is_ajax_post) {
            return false;
        }
        
        $plugin_action = $_GET['plugin_action'] ?? $_POST['plugin_action'] ?? null;
        
        switch ($plugin_action) {
            case 'get_teams':
                $this->handleGetTeamsRequest();
                return true;
                
            case 'batch_upload':  // NEU: Upload-Handler
                $this->handleBatchUploadRequest();
                return true;
                
            default:
                // Kein Plugin-spezifischer AJAX-Request
                return false;
        }
    }
    
    /**
     * PHASE 4: Team-Daten für AJAX-Request
     */
    private function handleGetTeamsRequest(): void
    {
        try {
            $assignment_id = $_GET['ass_id'] ?? $_POST['ass_id'] ?? null;
            
            if (!$assignment_id || !is_numeric($assignment_id)) {
                throw new Exception("Invalid or missing assignment ID");
            }
            
            $this->logger->info("Plugin UI: AJAX request for teams, assignment: $assignment_id");
            
            // Team-Data-Provider verwenden
            $team_provider = new ilExTeamDataProvider();
            $team_provider->generateJSONResponse((int)$assignment_id);
            
        } catch (Exception $e) {
            $this->logger->error("Plugin UI: Error in get teams request: " . $e->getMessage());
            
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 500 Internal Server Error');
            
            echo json_encode([
                'error' => true,
                'message' => 'Fehler beim Laden der Team-Daten',
                'details' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    /**
     * NEU: Batch Upload Request Handler
     */
    private function handleBatchUploadRequest(): void
    {
        try {
            $this->logger->info("Plugin UI: Batch upload request received");
            
            $assignment_id = $_POST['ass_id'] ?? null;
            
            if (!$assignment_id || !is_numeric($assignment_id)) {
                throw new Exception("Invalid assignment ID: " . var_export($assignment_id, true));
            }
            
            if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
                $upload_error = $_FILES['zip_file']['error'] ?? 'unknown';
                throw new Exception("No valid ZIP file uploaded. Upload error: " . $upload_error);
            }
            
            $uploaded_file = $_FILES['zip_file'];
            $this->logger->info("Plugin UI: Processing uploaded file: " . $uploaded_file['name'] . 
                               " Size: " . $uploaded_file['size'] . " bytes");
            
            // Upload-Handler verwenden
            $upload_handler = new ilExFeedbackUploadHandler();
            $upload_handler->handleFeedbackUpload([
                'assignment_id' => (int)$assignment_id,
                'tutor_id' => $GLOBALS['DIC']->user()->getId(),
                'zip_path' => $uploaded_file['tmp_name']
            ]);
            
            $this->logger->info("Plugin UI: Upload processing completed successfully");
            
            // Success Response
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Batch-Upload erfolgreich verarbeitet',
                'file' => $uploaded_file['name'],
                'size' => $uploaded_file['size']
            ], JSON_UNESCAPED_UNICODE);
            exit;
            
        } catch (Exception $e) {
            $this->logger->error("Plugin UI: Batch upload error: " . $e->getMessage());
            $this->logger->error("Plugin UI: Stack trace: " . $e->getTraceAsString());
            
            // Error Response
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 500 Internal Server Error');
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'Siehe ILIAS-Log für weitere Details'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    /**
     * PHASE 4: Batch-Download-Request verarbeiten
     */
    private function handleBatchDownloadRequest(): void
    {
        try {
            $assignment_id = $_POST['ass_id'] ?? null;
            $team_ids_string = $_POST['team_ids'] ?? '';
            
            if (!$assignment_id || !is_numeric($assignment_id)) {
                throw new Exception("Invalid assignment ID");
            }
            
            if (empty($team_ids_string)) {
                throw new Exception("No teams selected");
            }
            
            // Team-IDs parsen
            $team_ids = array_map('intval', explode(',', $team_ids_string));
            $team_ids = array_filter($team_ids, function($id) { return $id > 0; });
            
            if (empty($team_ids)) {
                throw new Exception("No valid team IDs provided");
            }
            
            $this->logger->info("Plugin UI: Batch download request - Assignment: $assignment_id, Teams: " . implode(',', $team_ids));
            
            // Batch-Download-Handler verwenden
            $batch_handler = new ilExBatchDownloadHandler();
            $batch_handler->generateBatchDownload((int)$assignment_id, $team_ids);
            
        } catch (Exception $e) {
            $this->logger->error("Plugin UI: Error in batch download request: " . $e->getMessage());
            
            global $DIC;
            $tpl = $DIC->ui()->mainTemplate();
            $tpl->setOnScreenMessage('failure', "Fehler beim Batch-Download: " . $e->getMessage(), true);
            
            // Redirect zurück zur Members-Seite
            $ctrl = $DIC->ctrl();
            $ctrl->redirect(null, 'members');
        }
    }
    
    /**
     * UI Rendering - Delegiert an Button Renderer
     */
    private function renderUI(?int $assignment_id): void
    {
        $renderer = new ilExTeamButtonRenderer();
        
        // PHASE 4: Enhanced JavaScript-Funktionen registrieren
        $renderer->registerGlobalJavaScriptFunctions();
        $renderer->addCustomCSS();
        
        if ($assignment_id === null) {
            $renderer->renderDebugBox();
            return;
        }
        
        // Assignment-Info prüfen
        $assignment_info = $this->getAssignmentInfo($assignment_id);
        
        if (strpos($assignment_info, '✅ IS TEAM') !== false) {
            // TEAM ASSIGNMENT -> Phase 4 Enhanced Button
            $renderer->renderTeamButton($assignment_id);
            $this->logger->info("Plugin UI: Rendered PHASE 4 TEAM button for assignment $assignment_id");
        } else {
            // INDIVIDUAL ASSIGNMENT -> Info-Box
            #$renderer->renderNonTeamInfo($assignment_id);
            #$this->logger->info("Plugin UI: Rendered NON-TEAM info for assignment $assignment_id");
        }
    }
    
    /**
     * Assignment-Info aus DB (Working Version)
     */
    private function getAssignmentInfo(int $assignment_id): string
    {
        try {
            global $DIC;
            $db = $DIC->database();
            
            $query = "SELECT exc_id, type FROM exc_assignment WHERE id = " . $db->quote($assignment_id, 'integer');
            $result = $db->query($query);
            
            if ($result->numRows() > 0) {
                $row = $db->fetchAssoc($result);
                $type = $row['type'];
                
                $is_team_assignment = ($type == 4);
                $team_status = $is_team_assignment ? "✅ IS TEAM" : "❌ NOT TEAM";
                
                $info = "DB OK: type=$type ($team_status)";
                $this->logger->info("Plugin UI: Assignment $assignment_id info: $info");
                return $info;
            }
            
            return "DB: Assignment not found";
            
        } catch (Exception $e) {
            $this->logger->error("Plugin UI: DB error: " . $e->getMessage());
            return "DB Error";
        }
    }
    
    /**
     * Feedback Download - Delegiert an Handler
     */
    protected function handleFeedbackDownload(array $parameters): void
    {
        $this->logger->info("Plugin UI: Delegating feedback download to handler");
        $handler = new ilExFeedbackDownloadHandler();
        $handler->handleFeedbackDownload($parameters);
    }

    /**
     * Feedback Upload - Delegiert an Handler
     */
    protected function handleFeedbackProcessing(array $parameters): void
    {
        $this->logger->info("Plugin UI: Delegating feedback processing to handler");
        $handler = new ilExFeedbackUploadHandler();
        $handler->handleFeedbackUpload($parameters);
    }
    
    /**
     * CLEANUP: Plugin-Ressourcen aufräumen
     */
    public function cleanup(): void
    {
        $this->logger->info("Plugin UI: Cleaning up Phase 4 resources");
        
        // Button-Renderer cleanup
        $renderer = new ilExTeamButtonRenderer();
        $renderer->cleanup();
        
        // Handler cleanup würde hier stehen, aber wir initialisieren sie nur bei Bedarf
        // Temp-Files werden automatisch über shutdown_function aufgeräumt
    }
    
    /**
     * DEBUG: Plugin-Status und -Statistiken für Phase 4
     */
    public function getPluginStatus(): array
    {
        $status = [
            'version' => '1.1.0',
            'phase' => '4 - Complete Team Multi-Feedback with Upload',
            'features' => [
                'assignment_detection' => 'Enhanced Multi-Strategy',
                'team_multi_feedback' => 'Full AJAX + Batch Download',
                'batch_upload' => 'ZIP Upload with Status Processing',
                'ui_rendering' => 'Modular Button Renderer',
                'download_processing' => 'Team + Individual Support',
                'upload_processing' => 'Status File Import/Export'
            ],
            'classes_loaded' => [
                'detector' => class_exists('ilExAssignmentDetector'),
                'button_renderer' => class_exists('ilExTeamButtonRenderer'),
                'download_handler' => class_exists('ilExFeedbackDownloadHandler'),
                'upload_handler' => class_exists('ilExFeedbackUploadHandler'),
                'team_data_provider' => class_exists('ilExTeamDataProvider'),
                'batch_download_handler' => class_exists('ilExBatchDownloadHandler')
            ]
        ];
        
        // Live Assignment Detection Test
        try {
            $detector = new ilExAssignmentDetector();
            $detected_id = $detector->detectAssignmentId();
            $status['current_detection'] = [
                'assignment_id' => $detected_id,
                'detection_stats' => $detector->getDetectionStats()
            ];
        } catch (Exception $e) {
            $status['current_detection'] = [
                'error' => $e->getMessage()
            ];
        }
        
        return $status;
    }
}
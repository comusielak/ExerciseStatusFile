<?php
declare(strict_types=1);

// Includes für alle Plugin-Klassen
require_once __DIR__ . '/Detection/class.ilExAssignmentDetector.php';
require_once __DIR__ . '/UI/class.ilExTeamButtonRenderer.php';
require_once __DIR__ . '/Processing/class.ilExFeedbackDownloadHandler.php';
require_once __DIR__ . '/Processing/class.ilExFeedbackUploadHandler.php';
require_once __DIR__ . '/Processing/class.ilExTeamDataProvider.php';
require_once __DIR__ . '/Processing/class.ilExMultiFeedbackDownloadHandler.php';

/**
 * Exercise Status File UI Hook
 * 
 * Hauptklasse für UI-Integration mit Team Multi-Feedback
 * Features: Assignment Detection, Team Multi-Feedback, Upload/Download
 * 
 * @author Cornel Musielak
 * @version 1.1.0
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
     * HTML-Hook Processing mit Multi-Feedback Download Support
     */
    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        $return = ["mode" => ilUIHookPluginGUI::KEEP, "html" => ""];

        // Multi-Feedback Download früh abfangen
        if (isset($_POST['plugin_action']) && $_POST['plugin_action'] === 'multi_feedback_download') {
            $this->handleMultiFeedbackDownloadRequest();
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
     * GUI Modifikation mit AJAX-Support
     */
    public function modifyGUI(string $a_comp, string $a_part, array $a_par = []): void
    {
            global $DIC;
            
            // AJAX-Requests abfangen
            if ($this->handleAJAXRequests()) {
                return;
            }
            
            $ctrl = $DIC->ctrl();
            $class = strtolower($ctrl->getCmdClass());
            $cmd = $ctrl->getCmd();
            
            // Nur in Exercise Management -> Members
            if ($class !== 'ilexercisemanagementgui' || $cmd !== 'members') {
                return;
            }
            
            // Assignment Detection
            $detector = new ilExAssignmentDetector();
            $assignment_id = $detector->detectAssignmentId();
            
            // UI-Rendering
            $this->renderUI($assignment_id);
    }
    
    /**
     * AJAX-Requests verarbeiten
     */
    private function handleAJAXRequests(): bool
    {
        $is_ajax_get = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                       $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
                       $_SERVER['REQUEST_METHOD'] === 'GET';
        
        $is_ajax_post = isset($_POST['plugin_action']);
        
        if (!$is_ajax_get && !$is_ajax_post) {
            return false;
        }
        
        $plugin_action = $_GET['plugin_action'] ?? $_POST['plugin_action'] ?? null;
        
        switch ($plugin_action) {
            case 'get_teams':
                $this->handleGetTeamsRequest();
                return true;
                
            case 'multi_feedback_upload':
                $this->handleMultiFeedbackUploadRequest();
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Team-Daten für AJAX-Request
     */
    private function handleGetTeamsRequest(): void
    {
        try {
            $assignment_id = $_GET['ass_id'] ?? $_POST['ass_id'] ?? null;
            
            if (!$assignment_id || !is_numeric($assignment_id)) {
                throw new Exception("Invalid or missing assignment ID");
            }
            
            $team_provider = new ilExTeamDataProvider();
            $team_provider->generateJSONResponse((int)$assignment_id);
            
        } catch (Exception $e) {
            $this->logger->error("Get teams request error: " . $e->getMessage());
            
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
     * Multi-Feedback Upload Request Handler
     */
    private function handleMultiFeedbackUploadRequest(): void
    {
        try {
            $assignment_id = $_POST['ass_id'] ?? null;
            
            if (!$assignment_id || !is_numeric($assignment_id)) {
                throw new Exception("Invalid assignment ID: " . var_export($assignment_id, true));
            }
            
            if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
                $upload_error = $_FILES['zip_file']['error'] ?? 'unknown';
                throw new Exception("No valid ZIP file uploaded. Upload error: " . $upload_error);
            }
            
            $uploaded_file = $_FILES['zip_file'];
            
            // Upload-Handler verwenden
            $upload_handler = new ilExFeedbackUploadHandler();
            $upload_handler->handleFeedbackUpload([
                'assignment_id' => (int)$assignment_id,
                'tutor_id' => $GLOBALS['DIC']->user()->getId(),
                'zip_path' => $uploaded_file['tmp_name']
            ]);
            
            // Success Response
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Multi-Feedback Upload erfolgreich verarbeitet',
                'file' => $uploaded_file['name'],
                'size' => $uploaded_file['size']
            ], JSON_UNESCAPED_UNICODE);
            exit;
            
        } catch (Exception $e) {
            $this->logger->error("Multi-Feedback upload error: " . $e->getMessage());
            
            // Error Response mit detaillierter Fehlermeldung
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 400 Bad Request');
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'Überprüfen Sie, ob die ZIP-Datei die korrekten Status-Files für dieses Assignment enthält.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    /**
     * Multi-Feedback Download Request Handler
     */
    private function handleMultiFeedbackDownloadRequest(): void
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
            
            // Multi-Feedback Download Handler verwenden
            $multi_feedback_handler = new ilExMultiFeedbackDownloadHandler();
            $multi_feedback_handler->generateMultiFeedbackDownload((int)$assignment_id, $team_ids);
            
        } catch (Exception $e) {
            $this->logger->error("Multi-Feedback download error: " . $e->getMessage());
            
            global $DIC;
            $tpl = $DIC->ui()->mainTemplate();
            $tpl->setOnScreenMessage('failure', "Fehler beim Multi-Feedback-Download: " . $e->getMessage(), true);
            
            // Redirect zurück zur Members-Seite
            $ctrl = $DIC->ctrl();
            $ctrl->redirect(null, 'members');
        }
    }
    
    /**
     * UI Rendering
     */
    private function renderUI(?int $assignment_id): void
    {
        $renderer = new ilExTeamButtonRenderer();
        
        // JavaScript-Funktionen registrieren
        $renderer->registerGlobalJavaScriptFunctions();
        $renderer->addCustomCSS();
        
        if ($assignment_id === null) {
            $renderer->renderDebugBox();
            return;
        }
        
        // Assignment-Info prüfen
        $assignment_info = $this->getAssignmentInfo($assignment_id);
        
        if (strpos($assignment_info, '✅ IS TEAM') !== false) {
            // Team Assignment -> Multi-Feedback Button
            $renderer->renderTeamButton($assignment_id);
        }
    }
    
    /**
     * Assignment-Info aus Datenbank
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
                
                return "DB OK: type=$type ($team_status)";
            }
            
            return "DB: Assignment not found";
            
        } catch (Exception $e) {
            $this->logger->error("Assignment info DB error: " . $e->getMessage());
            return "DB Error";
        }
    }
    
    /**
     * Feedback Download Handler
     */
    protected function handleFeedbackDownload(array $parameters): void
    {
        $handler = new ilExFeedbackDownloadHandler();
        $handler->handleFeedbackDownload($parameters);
    }

    /**
     * Feedback Upload Handler
     */
    protected function handleFeedbackProcessing(array $parameters): void
    {
        $handler = new ilExFeedbackUploadHandler();
        $handler->handleFeedbackUpload($parameters);
    }
    
    /**
     * Plugin-Ressourcen aufräumen
     */
    public function cleanup(): void
    {
        $renderer = new ilExTeamButtonRenderer();
        $renderer->cleanup();
    }
    
    /**
     * Plugin-Status und -Statistiken
     */
    public function getPluginStatus(): array
    {
        $status = [
            'version' => '1.1.0',
            'phase' => 'Complete Team Multi-Feedback',
            'features' => [
                'assignment_detection' => 'Multi-Strategy Detection',
                'team_multi_feedback' => 'Full AJAX + Multi-Feedback Download',
                'multi_feedback_upload' => 'ZIP Upload with Status Processing',
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
                'multi_feedback_download_handler' => class_exists('ilExMultiFeedbackDownloadHandler')
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
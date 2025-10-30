<?php
declare(strict_types=1);

/**
 * Assignment Detection Engine
 * 
 * Ermittelt die Assignment-ID aus verschiedenen Quellen
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExAssignmentDetector
{
    private ilLogger $logger;
    private ilCtrl $ctrl;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->ctrl = $DIC->ctrl();
    }
    
    /**
     * Hauptmethode: Assignment-ID ermitteln
     */
    public function detectAssignmentId(): ?int
    {
        // 1. Direkte Parameter
        $direct_result = $this->detectFromDirectParams();
        if ($direct_result) {
            // In Session speichern für zukünftige Requests
            $this->saveToSession($direct_result);
            return $direct_result;
        }

        // 2. Session
        $session_result = $this->detectFromSession();
        if ($session_result) {
            return $session_result;
        }

        // 3. Exercise Context
        $exercise_result = $this->detectFromExerciseContext();
        if ($exercise_result) {
            return $exercise_result;
        }

        return null;
    }

    /**
     * Assignment ID in Session speichern
     */
    private function saveToSession(int $assignment_id): void
    {
        try {
            $_SESSION['exc_status_file_last_assignment'] = $assignment_id;
        } catch (Exception) {
            // Fehler ignorieren - nicht kritisch
        }
    }
    
    /**
     * Detection über direkte Parameter
     */
    private function detectFromDirectParams(): ?int
    {
        global $DIC;

        $sources = [
            $_GET['ass_id'] ?? null,
            $_POST['ass_id'] ?? null,
            $DIC->http()->request()->getQueryParams()['ass_id'] ?? null,
            $DIC->http()->request()->getParsedBody()['ass_id'] ?? null
        ];

        foreach ($sources as $value) {
            if ($value && is_numeric($value)) {
                return (int)$value;
            }
        }

        return null;
    }

    /**
     * Detection über Session
     */
    private function detectFromSession(): ?int
    {
        try {
            $session_data = $_SESSION ?? [];

            // Unser eigener Session-Key (höchste Priorität)
            if (isset($session_data['exc_status_file_last_assignment']) &&
                is_numeric($session_data['exc_status_file_last_assignment'])) {
                return (int)$session_data['exc_status_file_last_assignment'];
            }

            $possible_keys = [
                'exc_assignment',
                'current_assignment',
                'selected_assignment',
                'ass_id',
                'exercise_assignment'
            ];

            foreach ($possible_keys as $key) {
                if (isset($session_data[$key]) && is_numeric($session_data[$key])) {
                    return (int)$session_data[$key];
                }
            }

            // Verschachtelte Arrays
            foreach ($session_data as $main_key => $value) {
                if (is_array($value) && strpos($main_key, 'exc') !== false) {
                    foreach ($value as $sub_key => $sub_value) {
                        if ($sub_key === 'ass_id' && is_numeric($sub_value)) {
                            return (int)$sub_value;
                        }
                    }
                }
            }

            return null;

        } catch (Exception $e) {
            $this->logger->error("Assignment detection session error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Detection über Exercise Context
     */
    private function detectFromExerciseContext(): ?int
    {
        global $DIC;
        
        $ref_id = $_GET['ref_id'] ?? $DIC->http()->request()->getQueryParams()['ref_id'] ?? null;
        if (!$ref_id || !is_numeric($ref_id)) {
            return null;
        }
        
        try {
            $obj = \ilObjectFactory::getInstanceByRefId((int)$ref_id);
            if (!$obj || !($obj instanceof \ilObjExercise)) {
                return null;
            }
            
            $assignments = \ilExAssignment::getInstancesByExercise($obj->getId());
            if (empty($assignments)) {
                return null;
            }
            
            // Nur ein Assignment -> nehmen
            if (count($assignments) === 1) {
                return array_values($assignments)[0]->getId();
            }

            // Mehrere Assignments: Erstes nehmen als Fallback
            // Hinweis: Bei mehreren Assignments sollte User explizit eins auswählen (über Dropdown)
            // Dieser Fallback wird nur nach frischem Login verwendet, bis User ein Assignment wählt
            return array_values($assignments)[0]->getId();
            
        } catch (Exception $e) {
            $this->logger->error("Assignment detection context error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cache leeren
     */
    public function clearCache(): void
    {
        // Für Kompatibilität
    }
    
    /**
     * Detection-Statistiken
     */
    public function getDetectionStats(): array
    {
        return [
            'version' => 'working',
            'cmd_class' => $this->ctrl->getCmdClass(),
            'cmd' => $this->ctrl->getCmd()
        ];
    }
}
?>
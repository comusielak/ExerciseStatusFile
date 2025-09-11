<?php
declare(strict_types=1);

/**
 * Assignment Detection Engine - WORKING SIMPLE VERSION
 * 
 * Basiert auf der funktionierenden Phase 1 Logic, aber als separate Klasse
 * Fokus: FUNKTIONIERT und findet Assignment-IDs zuverlässig
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - Working Simple
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
     * MAIN DETECTION - Basiert auf funktionierender Phase 1 Logic
     */
    public function detectAssignmentId(): ?int
    {
        $this->logger->info("Plugin Detector: Starting SIMPLE working detection");
        
        // 1. DIREKTE Parameter (funktioniert immer)
        $direct_result = $this->detectFromDirectParams();
        if ($direct_result) {
            $this->logger->info("Plugin Detector: Found via direct params: $direct_result");
            return $direct_result;
        }
        
        // 2. SESSION (aus Phase 1 - funktioniert)
        $session_result = $this->detectFromSession();
        if ($session_result) {
            $this->logger->info("Plugin Detector: Found via session: $session_result");
            return $session_result;
        }
        
        // 3. EXERCISE CONTEXT (aus Phase 1 - funktioniert)
        $exercise_result = $this->detectFromExerciseContext();
        if ($exercise_result) {
            $this->logger->info("Plugin Detector: Found via exercise context: $exercise_result");
            return $exercise_result;
        }
        
        $this->logger->warning("Plugin Detector: No assignment ID found");
        return null;
    }
    
    /**
     * Direct Parameters - PHASE 1 VERSION (funktioniert)
     */
    private function detectFromDirectParams(): ?int
    {
        global $DIC;
        
        // Standard GET/POST Parameter
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
     * Session Detection - PHASE 1 VERSION (funktioniert)
     */
    private function detectFromSession(): ?int
    {
        try {
            $session_data = $_SESSION ?? [];
            
            // Standard Session-Keys
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
            $this->logger->error("Plugin Detector: Session error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Exercise Context - PHASE 1 VERSION (funktioniert)
     */
    private function detectFromExerciseContext(): ?int
    {
        global $DIC;
        
        $ref_id = $_GET['ref_id'] ?? $DIC->http()->request()->getQueryParams()['ref_id'] ?? null;
        if (!$ref_id || !is_numeric($ref_id)) {
            return null;
        }
        
        try {
            // Exercise Objekt laden
            $obj = \ilObjectFactory::getInstanceByRefId((int)$ref_id);
            if (!$obj || !($obj instanceof \ilObjExercise)) {
                return null;
            }
            
            // Alle Assignments holen
            $assignments = \ilExAssignment::getInstancesByExercise($obj->getId());
            if (empty($assignments)) {
                return null;
            }
            
            // Wenn nur ein Assignment -> nehmen
            if (count($assignments) === 1) {
                return array_values($assignments)[0]->getId();
            }
            
            // Team-Assignment bevorzugen
            foreach ($assignments as $assignment) {
                if ($assignment->getType() == 4) { // Team Assignment
                    return $assignment->getId();
                }
            }
            
            // Fallback: Erstes Assignment
            return array_values($assignments)[0]->getId();
            
        } catch (Exception $e) {
            $this->logger->error("Plugin Detector: Exercise context error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Simple cache clear
     */
    public function clearCache(): void
    {
        // Für Kompatibilität - aktuell kein Cache
    }
    
    /**
     * Simple stats
     */
    public function getDetectionStats(): array
    {
        return [
            'version' => 'simple_working',
            'cmd_class' => $this->ctrl->getCmdClass(),
            'cmd' => $this->ctrl->getCmd()
        ];
    }
}
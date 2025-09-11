<?php
declare(strict_types=1);

class ilExerciseStatusFilePlugin extends ilUserInterfaceHookPlugin
{
    const PLUGIN_ID = "exstatusfile";
    const PLUGIN_NAME = "ExerciseStatusFile";

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    public function getPluginDirectory(): string
    {
        return "ExerciseStatusFile";
    }

    protected function init(): void
    {
        parent::init();
    }

    public function getUIClassInstance(): ilExerciseStatusFileUIHookGUI
    {
        return new ilExerciseStatusFileUIHookGUI($this);
    }
    
    public function isActive(): bool
    {
        return parent::isActive();
    }

    /**
     * ERWEITERTE DEPENDENCY CHECKS für Phase 1
     * Prüft alle benötigten ILIAS-Klassen vor Plugin-Aktivierung
     */
    protected function beforeActivation(): bool
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        $required_classes = [
            'ilExAssignment' => 'Exercise Assignment Core',
            'ilExcel' => 'Excel Processing',
            'ilExAssignmentMemberStatus' => 'Assignment Member Status',
            'ilExAssignmentTeam' => 'Team Assignment Support',
            'ilObjExercise' => 'Exercise Object',
            'ilExerciseException' => 'Exercise Exception Handling'
        ];
        
        $missing_classes = [];
        
        foreach ($required_classes as $class_name => $description) {
            if (!class_exists($class_name)) {
                $missing_classes[] = "$class_name ($description)";
                $logger->error("Plugin ExerciseStatusFile: Missing required class: $class_name");
            }
        }
        
        if (!empty($missing_classes)) {
            $logger->error("Plugin ExerciseStatusFile: Cannot activate - missing dependencies: " . 
                          implode(', ', $missing_classes));
            return false;
        }
        
        // Zusätzliche Prüfungen für Phase 1
        try {
            // Prüfe ob ZipArchive verfügbar ist
            if (!class_exists('ZipArchive')) {
                $logger->error("Plugin ExerciseStatusFile: ZipArchive extension not available");
                return false;
            }
            
            // Prüfe ob PhpSpreadsheet verfügbar ist (für Excel-Support)
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                $logger->warning("Plugin ExerciseStatusFile: PhpSpreadsheet not available - Excel support limited");
                // Nicht kritisch, Plugin kann auch ohne Excel funktionieren
            }
            
            // Prüfe temp-Directory-Zugriff
            $temp_test = sys_get_temp_dir() . '/plugin_test_' . uniqid();
            if (!@mkdir($temp_test, 0777, true)) {
                $logger->error("Plugin ExerciseStatusFile: Cannot create temporary directories");
                return false;
            }
            @rmdir($temp_test);
            
            $logger->info("Plugin ExerciseStatusFile: All dependency checks passed");
            return true;
            
        } catch (Exception $e) {
            $logger->error("Plugin ExerciseStatusFile: Error in dependency check: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NEU: After Activation Hook für Logging
     */
    protected function afterActivation(): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        $logger->info("Plugin ExerciseStatusFile v1.1.0 activated successfully - Enhanced Assignment Detection enabled");
    }

    /**
     * NEU: After Deactivation Hook für Cleanup
     */
    protected function afterDeactivation(): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        
        // Session-Cleanup
        if (isset($_SESSION['exc_status_files_processed'])) {
            unset($_SESSION['exc_status_files_processed']);
        }
        
        $logger->info("Plugin ExerciseStatusFile deactivated and cleaned up");
    }
}
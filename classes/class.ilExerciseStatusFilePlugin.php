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

// ENTFERNE die ModifyGUI Methoden und verwende nur:
    public function getUIClassInstance(): ilExerciseStatusFileUIHookGUI
    {
        return new ilExerciseStatusFileUIHookGUI($this);
    }
    public function isActive(): bool
    {
        return parent::isActive();
    }

    protected function beforeActivation(): bool
    {
        return class_exists('ilExAssignment') && 
               class_exists('ilExcel') &&
               class_exists('ilExAssignmentMemberStatus');
    }


}
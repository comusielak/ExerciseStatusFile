<?php
declare(strict_types=1);

/**
 * User Data Provider
 * 
 * Stellt User-Daten für Individual-Assignments bereit (analog zu Team Data Provider)
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExUserDataProvider
{
    private ilLogger $logger;
    private ilDBInterface $db;
    private ilExerciseStatusFilePlugin $plugin;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->db = $DIC->database();
        
        // Plugin-Instanz für Übersetzungen
        $plugin_id = 'exstatusfile';

        $repo = $DIC['component.repository'];
        $factory = $DIC['component.factory'];

        $info = $repo->getPluginById($plugin_id);
        if ($info !== null && $info->isActive()) {
            $this->plugin = $factory->getPlugin();
        }
    }
    
    /**
     * Users für Assignment laden
     */
    public function getUsersForAssignment(int $assignment_id): array
    {
        try {
            $assignment = new \ilExAssignment($assignment_id);
            
            // Nur für Individual-Assignments
            if ($assignment->getAssignmentType()->usesTeams()) {
                return [];
            }
            
            $exercise_id = $assignment->getExerciseId();
            
            // Alle Exercise-Mitglieder holen
            $query = "SELECT usr_id FROM exc_members WHERE obj_id = " . 
                     $this->db->quote($exercise_id, 'integer');
            $result = $this->db->query($query);
            
            $users_data = [];
            while ($row = $this->db->fetchAssoc($result)) {
                $user_id = (int)$row['usr_id'];
                $user_data = $this->buildUserData($user_id, $assignment);
                
                if ($user_data) {
                    $users_data[] = $user_data;
                }
            }
            
            // Sortieren nach Nachname
            usort($users_data, function($a, $b) {
                return strcmp($a['lastname'], $b['lastname']);
            });
            
            return $users_data;
            
        } catch (Exception $e) {
            $this->logger->error("User data provider error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * User-Daten erstellen (mit robusterem Submission-Check)
     */
    private function buildUserData(int $user_id, \ilExAssignment $assignment): ?array
    {
        try {
            // User-Daten laden
            $user_data = \ilObjUser::_lookupName($user_id);
            if (!$user_data || !$user_data['login']) {
                return null;
            }
            
            // Status ermitteln
            $user_status = $this->getUserStatus($user_id, $assignment);
            
            // Submission prüfen - VERBESSERTE VERSION
            $has_submission = $this->checkSubmissionExists($user_id, $assignment);
            
            return [
                'user_id' => $user_id,
                'login' => $user_data['login'],
                'firstname' => $user_data['firstname'],
                'lastname' => $user_data['lastname'],
                'fullname' => trim($user_data['firstname'] . ' ' . $user_data['lastname']),
                'status' => $user_status['status'],
                'mark' => $user_status['mark'],
                'notice' => $user_status['notice'],
                'comment' => $user_status['comment'],
                'has_submission' => $has_submission
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Error building user data for user $user_id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * User-Status ermitteln
     */
    private function getUserStatus(int $user_id, \ilExAssignment $assignment): array
    {
        try {
            $member_status = $assignment->getMemberStatus($user_id);
            
            if ($member_status) {
                return [
                    'status' => $this->translateStatus($member_status->getStatus()),
                    'mark' => $member_status->getMark() ?: '',
                    'notice' => $member_status->getNotice() ?: '',
                    'comment' => $member_status->getComment() ?: ''
                ];
            }
            
            return $this->getDefaultStatus();
            
        } catch (Exception $e) {
            $this->logger->error("Error getting user status: " . $e->getMessage());
            return $this->getDefaultStatus();
        }
    }
    
    /**
     * Prüfen ob User eine Submission hat - NEUE ROBUSTE METHODE
     */
    private function checkSubmissionExists(int $user_id, \ilExAssignment $assignment): bool
    {
        try {
            $assignment_id = $assignment->getId();
            
            // Methode 1: Direkter DB-Check in exc_returned (zuverlässigste Methode)
            $query = "SELECT COUNT(*) as cnt FROM exc_returned 
                      WHERE ass_id = " . $this->db->quote($assignment_id, 'integer') . " 
                      AND user_id = " . $this->db->quote($user_id, 'integer');
            
            $result = $this->db->query($query);
            if ($row = $this->db->fetchAssoc($result)) {
                if ((int)$row['cnt'] > 0) {
                    return true;
                }
            }
            
            // Methode 2: Check über ilExSubmission Objekt
            try {
                $submission = new \ilExSubmission($assignment, $user_id);
                
                if ($submission && $submission->hasSubmitted()) {
                    return true;
                }
                
                // Prüfe auch Files
                $files = $submission->getFiles();
                if (!empty($files) && is_array($files) && count($files) > 0) {
                    return true;
                }
            } catch (Exception $e) {
                // Submission-Objekt konnte nicht erstellt werden - ignorieren
            }
            
            // Methode 3: Check über MemberStatus
            try {
                $member_status = $assignment->getMemberStatus($user_id);
                if ($member_status) {
                    $returned_obj = $member_status->getReturned();
                    if ($returned_obj && method_exists($returned_obj, 'getTimestamp')) {
                        $timestamp = $returned_obj->getTimestamp();
                        if ($timestamp && $timestamp > 0) {
                            return true;
                        }
                    }
                }
            } catch (Exception $e) {
                // MemberStatus konnte nicht geladen werden - ignorieren
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Error checking submission for user $user_id in assignment {$assignment->getId()}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Status übersetzen
     */
    private function translateStatus(?string $status): string
    {
        switch ($status) {
            case 'passed':
                return $this->plugin->txt('status_passed');
            case 'failed':
                return $this->plugin->txt('status_failed');
            case 'notgraded':
            default:
                return $this->plugin->txt('status_notgraded');
        }
    }
    
    /**
     * Standard-Status
     */
    private function getDefaultStatus(): array
    {
        return [
            'status' => $this->plugin->txt('status_notgraded'),
            'mark' => '',
            'notice' => '',
            'comment' => ''
        ];
    }
    
    /**
     * JSON-Response für AJAX generieren
     */
    public function generateJSONResponse(int $assignment_id): void
    {
        try {
            $users_data = $this->getUsersForAssignment($assignment_id);
            
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo json_encode([
                'success' => true,
                'users' => $users_data
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            $this->logger->error("Error generating JSON response: " . $e->getMessage());
            
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 500 Internal Server Error');
            
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => $this->plugin->txt('individual_error_loading'),
                'details' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
?>
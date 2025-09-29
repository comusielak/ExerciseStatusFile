<?php
declare(strict_types=1);

/**
 * Team Data Provider
 * 
 * Stellt Team-Daten für AJAX-Requests bereit
 * Verwendet nur stabile ILIAS-APIs
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExTeamDataProvider
{
    private ilLogger $logger;
    private ilDBInterface $db;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->db = $DIC->database();
    }
    
    /**
     * Teams für Assignment laden
     */
    public function getTeamsForAssignment(int $assignment_id): array
    {
        try {
            $assignment = new \ilExAssignment($assignment_id);
            if (!$assignment->getAssignmentType()->usesTeams()) {
                return [];
            }
            
            $teams = ilExAssignmentTeam::getInstancesFromMap($assignment_id);
            if (empty($teams)) {
                return [];
            }
            
            $teams_data = [];
            foreach ($teams as $team_id => $team) {
                $team_data = $this->buildTeamData($team, $assignment);
                if ($team_data) {
                    $teams_data[] = $team_data;
                }
            }
            
            return $teams_data;
            
        } catch (Exception $e) {
            $this->logger->error("Team data provider error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Team-Daten erstellen
     */
    private function buildTeamData(ilExAssignmentTeam $team, \ilExAssignment $assignment): ?array
    {
        try {
            $team_id = $team->getId();
            $member_ids = $team->getMembers();
            
            if (empty($member_ids)) {
                return null;
            }
            
            $members_data = [];
            foreach ($member_ids as $user_id) {
                $member_data = $this->getMemberData($user_id);
                if ($member_data) {
                    $members_data[] = $member_data;
                }
            }
            
            if (empty($members_data)) {
                return null;
            }
            
            $team_status = $this->getTeamStatus($team, $assignment);
            
            return [
                'team_id' => $team_id,
                'member_count' => count($members_data),
                'members' => $members_data,
                'status' => $team_status['status'],
                'mark' => $team_status['mark'],
                'notice' => $team_status['notice'],
                'comment' => $team_status['comment'],
                'last_submission' => null,
                'has_submissions' => false
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Error building team data for team " . $team->getId() . ": " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Mitglieder-Daten laden
     */
    private function getMemberData(int $user_id): ?array
    {
        try {
            $user_data = \ilObjUser::_lookupName($user_id);
            if (!$user_data || !$user_data['login']) {
                return null;
            }
            
            return [
                'user_id' => $user_id,
                'login' => $user_data['login'],
                'firstname' => $user_data['firstname'],
                'lastname' => $user_data['lastname'],
                'fullname' => trim($user_data['firstname'] . ' ' . $user_data['lastname'])
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Error loading member data for user $user_id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Team-Status ermitteln
     */
    private function getTeamStatus(ilExAssignmentTeam $team, \ilExAssignment $assignment): array
    {
        try {
            $member_ids = $team->getMembers();
            if (empty($member_ids)) {
                return $this->getDefaultStatus();
            }
            
            $first_member_id = reset($member_ids);
            
            try {
                $member_status = $assignment->getMemberStatus($first_member_id);
                
                if ($member_status) {
                    return [
                        'status' => $this->translateStatus($member_status->getStatus()),
                        'mark' => $member_status->getMark() ?: '',
                        'notice' => $member_status->getNotice() ?: '',
                        'comment' => $member_status->getComment() ?: ''
                    ];
                }
            } catch (Exception $e) {
                // Fallback bei Problemen
            }
            
            return $this->getDefaultStatus();
            
        } catch (Exception $e) {
            $this->logger->error("Error getting team status: " . $e->getMessage());
            return $this->getDefaultStatus();
        }
    }
    
    /**
     * Status übersetzen
     */
    private function translateStatus(?string $status): string
    {
        switch ($status) {
            case 'passed':
                return 'Bestanden';
            case 'failed':
                return 'Nicht bestanden';
            case 'notgraded':
            default:
                return 'Nicht bewertet';
        }
    }
    
    /**
     * Standard-Status
     */
    private function getDefaultStatus(): array
    {
        return [
            'status' => 'Nicht bewertet',
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
            $teams_data = $this->getTeamsForAssignment($assignment_id);
            
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo json_encode($teams_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            $this->logger->error("Error generating JSON response: " . $e->getMessage());
            
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
     * Team-Daten für Debugging
     */
    public function getTeamsDebugInfo(int $assignment_id): array
    {
        return [
            'assignment_id' => $assignment_id,
            'teams_count' => count($this->getTeamsForAssignment($assignment_id)),
            'teams_data' => $this->getTeamsForAssignment($assignment_id),
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.1.0'
        ];
    }
}
?>
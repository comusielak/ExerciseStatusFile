<?php
declare(strict_types=1);

/**
 * Team Data Provider - SIMPLIFIED VERSION
 * 
 * Stellt Team-Daten für AJAX-Requests bereit
 * SAFE: Verwendet nur stabile ILIAS-APIs
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - Simplified
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
     * MAIN: Teams für Assignment laden (SIMPLIFIED)
     */
    public function getTeamsForAssignment(int $assignment_id): array
    {
        $this->logger->info("Plugin TeamData: Loading teams for assignment $assignment_id (simplified)");
        
        try {
            // Assignment validieren
            $assignment = new \ilExAssignment($assignment_id);
            if (!$assignment->getAssignmentType()->usesTeams()) {
                $this->logger->warning("Plugin TeamData: Assignment $assignment_id is not a team assignment");
                return [];
            }
            
            // Teams laden
            $teams = ilExAssignmentTeam::getInstancesFromMap($assignment_id);
            if (empty($teams)) {
                $this->logger->info("Plugin TeamData: No teams found for assignment $assignment_id");
                return [];
            }
            
            $teams_data = [];
            foreach ($teams as $team_id => $team) {
                $team_data = $this->buildSimpleTeamData($team, $assignment);
                if ($team_data) {
                    $teams_data[] = $team_data;
                }
            }
            
            $this->logger->info("Plugin TeamData: Loaded " . count($teams_data) . " teams (simplified)");
            return $teams_data;
            
        } catch (Exception $e) {
            $this->logger->error("Plugin TeamData: Error loading teams: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * SIMPLIFIED: Team-Daten ohne problematische APIs
     */
    private function buildSimpleTeamData(ilExAssignmentTeam $team, \ilExAssignment $assignment): ?array
    {
        try {
            $team_id = $team->getId();
            $member_ids = $team->getMembers();
            
            if (empty($member_ids)) {
                $this->logger->warning("Plugin TeamData: Team $team_id has no members");
                return null;
            }
            
            // Team-Mitglieder laden (SAFE)
            $members_data = [];
            foreach ($member_ids as $user_id) {
                $member_data = $this->getMemberData($user_id);
                if ($member_data) {
                    $members_data[] = $member_data;
                }
            }
            
            if (empty($members_data)) {
                $this->logger->warning("Plugin TeamData: No valid members found for team $team_id");
                return null;
            }
            
            // Team-Status (SIMPLIFIED)
            $team_status = $this->getSimpleTeamStatus($team, $assignment);
            
            return [
                'team_id' => $team_id,
                'member_count' => count($members_data),
                'members' => $members_data,
                'status' => $team_status['status'],
                'mark' => $team_status['mark'],
                'notice' => $team_status['notice'],
                'comment' => $team_status['comment'],
                'last_submission' => null, // REMOVED: Problematic submission detection
                'has_submissions' => false // SIMPLIFIED: Always false for now
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Plugin TeamData: Error building team data for team " . $team->getId() . ": " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * SAFE: Mitglieder-Daten laden
     */
    private function getMemberData(int $user_id): ?array
    {
        try {
            $user_data = \ilObjUser::_lookupName($user_id);
            if (!$user_data || !$user_data['login']) {
                $this->logger->warning("Plugin TeamData: Invalid user data for user $user_id");
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
            $this->logger->error("Plugin TeamData: Error loading member data for user $user_id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * SIMPLIFIED: Team-Status ohne problematische APIs
     */
    private function getSimpleTeamStatus(ilExAssignmentTeam $team, \ilExAssignment $assignment): array
    {
        try {
            // Status vom ersten Team-Mitglied nehmen
            $member_ids = $team->getMembers();
            if (empty($member_ids)) {
                return $this->getDefaultStatus();
            }
            
            $first_member_id = reset($member_ids);
            
            // SAFE: Member Status über Assignment
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
                $this->logger->debug("Plugin TeamData: Could not get member status: " . $e->getMessage());
            }
            
            return $this->getDefaultStatus();
            
        } catch (Exception $e) {
            $this->logger->error("Plugin TeamData: Error getting team status: " . $e->getMessage());
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
     * Default Status
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
            
            // HTTP-Headers für JSON
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            // JSON-Response senden
            echo json_encode($teams_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            $this->logger->error("Plugin TeamData: Error generating JSON response: " . $e->getMessage());
            
            // Error-Response
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
     * Debug: Team-Daten als Array zurückgeben
     */
    public function getTeamsDebugInfo(int $assignment_id): array
    {
        return [
            'assignment_id' => $assignment_id,
            'teams_count' => count($this->getTeamsForAssignment($assignment_id)),
            'teams_data' => $this->getTeamsForAssignment($assignment_id),
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => 'simplified'
        ];
    }
}
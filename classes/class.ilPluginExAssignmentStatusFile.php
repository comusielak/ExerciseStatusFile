<?php
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ILIAS\Logging\Logger;

class ilPluginExAssignmentStatusFile extends ilExcel
{
    const FORMAT_CSV = "Csv";

    protected $assignment;
    protected string $format;
    protected $members = [];
    protected $teams = [];
    protected $member_titles =  ['update','usr_id','login','lastname','firstname','status','mark','notice','comment', 'plagiarism', 'plag_comment'];
    protected $team_titles =  ['update','team_id','logins','status','mark','notice','comment', 'plagiarism', 'plag_comment'];
    protected $valid_states = ['notgraded','passed','failed'];
    protected $valid_plag_flags = ['none','suspicion','detected'];
    public $updates = [];
    protected $updates_applied = false;
    protected $error;
    protected $allow_plag_update = false;
    protected bool $loadfromfile_success = false;
    protected bool $writetofile_success = false;
    protected ilLogger $log;

    public function init(ilExAssignment $assignment, $user_ids = null) {
        $this->members = [];
        $this->teams = [];
        $this->updates = [];
        $this->updates_applied = false;
        $this->error = null;
        $this->assignment = $assignment;

        global $DIC;
        $this->log = $DIC->logger()->root();        

        $this->initMembers($user_ids);
        if ($this->assignment->getAssignmentType()->usesTeams()) {
            $this->initTeams($user_ids);
        }
    }

    public function allowPlagiarismUpdate($allow = true) {
        $this->allow_plag_update = (bool) $allow;
    }

    public function getValidFormats(): array {
        return array(self::FORMAT_XML, self::FORMAT_BIFF, self::FORMAT_CSV);
    }

    public function getFilename() {
        switch($this->format) {
            case self::FORMAT_XML:
                return "status.xlsx";
            case self::FORMAT_BIFF:
                return "status.xls";
            case self::FORMAT_CSV:
            default:
                return "status.csv";
        }
    }

    public function loadFromFile(string $filename): void {
        $this->error = false;
        $this->updates = [];
        try {
            if (file_exists($filename)) {
                $this->workbook = IOFactory::load($filename);
                if ($this->assignment->getAssignmentType()->usesTeams()) {
                    $this->loadTeamSheet();
                }
                else {
                    $this->loadMemberSheet();
                }
                $this->loadfromfile_success = true;
                return;
            }
            $this->loadfromfile_success = false;
            return;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            $this->loadfromfile_success = false;
            return;
        }
    }

    public function isWriteToFileSuccess(): bool {
        return $this->writetofile_success;
    }

    public function isLoadFromFileSuccess(): bool {
        return $this->loadfromfile_success;
    }

    public function writeToFile($a_file): void {
        try {
            if ($this->assignment->getAssignmentType()->usesTeams()) {
                $this->writeTeamSheet();
            }
            else {
                $this->writeMemberSheet();
            }
            $writer = IOFactory::createWriter($this->workbook, $this->format);
            $writer->save($a_file);
            $this->writetofile_success = true;
            return;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            $this->writetofile_success = false;
            return;
        }
    }

    protected function prepareString($a_value): string
    {
        return $a_value;
    }

    protected function initMembers($usr_ids = null) {
        $members = $this->assignment->getMemberListData();

        if (isset($usr_ids)) {
            foreach ($members as $id => $member) {
                if (in_array($id, $usr_ids)) {
                    $this->members[$id] = $member;
                }
            }
        }
        else {
            $this->members = $members;
        }
    }

    protected function initTeams($usr_ids = null) {
        $teams = ilExAssignmentTeam::getInstancesFromMap($this->assignment->getId());

        if (isset($usr_ids)) {
            foreach ($teams as $id => $team) {
                if (count(array_intersect($team->getMembers(), array_keys($this->members))) > 0) {
                    $this->teams[$id] = $team;
                }
            }
        }
        else {
            $this->teams = $teams;
        }
    }

    protected function writeMemberSheet() {
        $this->addSheet('members');

        $col = 0;
        foreach ($this->member_titles as $title) {
            $this->setCell(1, $col++, $title);
        }

        $row = 2;
        foreach ($this->members as $member) {
            $col = 0;
            $this->setCell($row, $col++, 0, DataType::TYPE_NUMERIC);
            $this->setCell($row, $col++, $member['usr_id'], DataType::TYPE_NUMERIC);
            $this->setCell($row, $col++, $member['login'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['lastname'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['firstname'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['status'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['mark'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['notice'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['comment'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, ($member['plag_flag'] == 'none' ? '' : $member['plag_flag']), DataType::TYPE_STRING);
            $this->setCell($row, $col, $member['plag_comment'], DataType::TYPE_STRING);
            $row++;
        }
    }

    protected function loadMemberSheet() {
        $sheet = $this->getSheetAsArray();

        $titles = array_shift($sheet);
        if (count(array_diff($this->member_titles, (array) $titles)) > 0) {
            throw new ilExerciseException("Status file has wrong column titles");
        }

        $index = array_flip($this->member_titles);
        
        foreach ($sheet as $rowdata) {
            $data = [];
            $data['update'] = (bool)  $rowdata[$index['update']];
            $data['login'] = (string) $rowdata[$index['login']];
            $data['usr_id'] = (int) $rowdata[$index['usr_id']];
            $data['status'] = (string) $rowdata[$index['status']];
            $data['mark'] = (string) $rowdata[$index['mark']];
            $data['notice'] = (string) $rowdata[$index['notice']];
            $data['comment'] = (string) $rowdata[$index['comment']];
            $data['plag_flag'] = ((string) $rowdata[$index['plagiarism']] ? (string) $rowdata[$index['plagiarism']] : 'none');
            $data['plag_comment'] = (string) $rowdata[$index['plag_comment']];
            
            $this->log->info("Plugin StatusFile: Processing row - " . implode(", ", $rowdata));
            
            if (!$data['update'] || !isset($this->members[$data['usr_id']])) {
                continue;
            }

            $this->checkRowData($data);
            $this->updates[] = $data;
        }
    }

    protected function writeTeamSheet() {
        $this->addSheet('teams');

        $col = 0;
        foreach ($this->team_titles as $title) {
            $this->setCell(1, $col++, $title);
        }

        $row = 2;
        foreach ($this->teams as $team) {
            $logins = [];
            $member = [];
            foreach ($team->getMembers() as $usr_id) {
                if (isset($this->members[$usr_id])) {
                    $logins[] = $this->members[$usr_id]['login'];
                    $member = $this->members[$usr_id];
                }
                else {
                    $logins[] = \ilObjUser::_lookupLogin($usr_id);
                }
            }

            $col = 0;
            $this->setCell($row, $col++, 0, DataType::TYPE_NUMERIC);
            $this->setCell($row, $col++, $team->getId(), DataType::TYPE_NUMERIC);
            $this->setCell($row, $col++, implode(', ', $logins), DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['status'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['mark'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['notice'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, $member['comment'], DataType::TYPE_STRING);
            $this->setCell($row, $col++, ($member['plag_flag'] == 'none' ? '' : $member['plag_flag']), DataType::TYPE_STRING);
            $this->setCell($row, $col, $member['plag_comment'], DataType::TYPE_STRING);
            $row++;
        }
    }

    protected function loadTeamSheet() {
        $sheet = $this->getSheetAsArray();

        $titles = array_shift($sheet);
        if (count(array_diff($this->team_titles, (array) $titles)) > 0) {
            throw new ilExerciseException("Team status file has wrong column titles");
        }

        $index = array_flip($this->team_titles);
        foreach ($sheet as $rowdata) {
            $data = [];
            $data['update'] = (bool)  $rowdata[$index['update']];
            $data['team_id'] = (string) $rowdata[$index['team_id']];
            $data['status'] = (string) $rowdata[$index['status']];
            $data['mark'] = (string) $rowdata[$index['mark']];
            $data['notice'] = (string) $rowdata[$index['notice']];
            $data['comment'] = (string) $rowdata[$index['comment']];
            $data['plag_flag'] = ((string) $rowdata[$index['plagiarism']] ? (string) $rowdata[$index['plagiarism']] : 'none');
            $data['plag_comment'] = (string) $rowdata[$index['plag_comment']];

            if (!$data['update'] || !isset($this->teams[$data['team_id']])) {
                continue;
            }

            $this->checkRowData($data);
            $this->updates[] = $data;
        }
    }

    protected function checkRowData($data) {
        if (!in_array($data['status'], $this->valid_states)) {
            throw new ilExerciseException("Invalid status: " . $data['status']);
        }
    }

    public function applyStatusUpdates() {
        $this->log->info('Plugin StatusFile: Apply status updates - START');
        $this->log->info('Plugin StatusFile: Number of updates to apply: ' . count($this->updates));

        foreach ($this->updates as $i => $data) {
            $this->log->info("Plugin StatusFile: Processing update $i: " . json_encode($data));
            
            $user_ids = [];
            if (isset($data['usr_id'])) {
                $user_ids = [$data['usr_id']];
            } elseif (isset($data['team_id'])) {
                $user_ids = $this->teams[$data['team_id']]->getMembers();
            }

            $this->log->info("Plugin StatusFile: Updating user IDs: " . implode(', ', $user_ids));

            foreach ($user_ids as $user_id) {
                $this->log->info("Plugin StatusFile: Updating status for user $user_id");
                
                $status = new ilExAssignmentMemberStatus($this->assignment->getId(), $user_id);
                $status->setStatus($data['status']);
                $status->setMark($data['mark']);
                $status->setComment($data['comment']);
                $status->setNotice($data['notice']);
                
                if ($this->allow_plag_update) {
                    // TODO: Plagiarism flags when available
                }
                
                $status->update();
                $this->log->info("Plugin StatusFile: Status updated for user $user_id");
            }
        }
        
        $this->updates_applied = true;
        $this->log->info('Plugin StatusFile: Apply status updates - FINISHED');
    }

    public function hasError() {
        return !empty($this->error);
    }

    public function hasUpdates() {
        return !empty($this->updates);
    }

    public function getInfo() {
        if ($this->hasError()) {
            return "Status file error in " . $this->getFilename() . ": " . $this->error;
        }
        elseif (!$this->hasUpdates()) {
            return "No updates found in " . $this->getFilename();
        }
        else {
            $list = [];
            foreach ($this->updates as $data) {
                $list[] = (empty($this->teams) ? $data['login'] : 'Team ' . $data['team_id']);
            }

            if ($this->updates_applied) {
                $type = empty($this->teams) ? 'users' : 'teams';
                return "Status updates applied for $type: " . implode(', ', $list);
            }
            else {
                $type = empty($this->teams) ? 'users' : 'teams';
                return "Status updates found for $type in " . $this->getFilename() . ": " . implode(', ', $list);
            }
        }
    }

    public function removeMemberSheet(){
        if ($this->workbook && $this->workbook->getSheetByName('members')) {
            $this->workbook->removeSheetByIndex(
                $this->workbook->getIndex(
                    $this->workbook->getSheetByName('members')
                )
            );
        }
    }
}
?>
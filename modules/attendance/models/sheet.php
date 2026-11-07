<?php
/**
 * @filesource modules/attendance/models/sheet.php
 *
 * เวลาเรียนรายคาบ: รายชื่อนักเรียนของรายวิชา (จากการลงทะเบียน) และสถานะการเข้าเรียน
 */

namespace Attendance\Sheet;

class Model extends \Kotchasan\Model
{
    /** สถานะที่บันทึกได้ (ตามประเภทการเข้าเรียนของระบบเดิม: มาเรียน มาสาย ลากิจ ลาป่วย ขาดเรียน กิจกรรม หนี ซ่อมเสริม) */
    const STATUSES = ['present', 'late', 'leave', 'sick', 'absent', 'activity', 'skip', 'remedial'];

    /** สถานะที่นับเป็นเวลาเรียน */
    const PRESENT = ['present', 'late', 'activity', 'remedial'];

    /**
     * สร้างตารางให้ไซต์ที่ติดตั้งไว้ก่อนมีโมดูลนี้ (ตรวจครั้งเดียวต่อเครื่อง)
     */
    public static function ensureTable()
    {
        $marker = sys_get_temp_dir().'/sms-attendance-table-ok';
        if (is_file($marker)) {
            return;
        }
        $db = \Kotchasan\DB::create();
        $table = $db->getTableName('attendance');
        $db->raw('CREATE TABLE IF NOT EXISTS `'.$table.'` ('
            .'`id` int(11) NOT NULL AUTO_INCREMENT, `course_id` int(11) NOT NULL, `room` int(11) NOT NULL DEFAULT 0,'
            .' `date` date NOT NULL, `period` tinyint(2) NOT NULL, `student_id` int(11) NOT NULL, `status` varchar(10) NOT NULL,'
            .' `teacher_id` int(11) NOT NULL DEFAULT 0, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`),'
            .' UNIQUE KEY `sheet` (`course_id`,`date`,`period`,`student_id`), KEY `course_room` (`course_id`,`room`)'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        @touch($marker);
    }

    /**
     * นักเรียนที่ลงทะเบียนรายวิชา (room = 0 ทุกห้อง)
     *
     * @return array [{id, number, code, name, room}]
     */
    public static function students($course_id, $room)
    {
        $where = [['G.course_id', (int) $course_id]];
        if ($room > 0) {
            $where[] = ['G.room', (int) $room];
        }
        $rows = static::createQuery()
            ->select('G.student_id id', 'G.number', 'S.student_id code', 'U.name', 'G.room')
            ->from('grade G')
            ->join('student S', [['S.id', 'G.student_id']], 'LEFT')
            ->join('user U', [['U.id', 'G.student_id']], 'LEFT')
            ->where($where)
            ->orderBy('G.room')
            ->orderBy('G.number')
            ->orderBy('S.student_id')
            ->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'number' => $r->number === null ? null : (int) $r->number,
                'code' => (string) $r->code,
                'name' => (string) $r->name,
                'room' => (int) $r->room
            ];
        }

        return $out;
    }

    /**
     * สถานะของคาบหนึ่ง
     *
     * @return array [student_id => status] และเวลาบันทึกล่าสุด
     */
    public static function marks($course_id, $date, $period)
    {
        $rows = static::createQuery()
            ->select('student_id', 'status', 'updated_at')
            ->from('attendance')
            ->where([['course_id', (int) $course_id], ['date', $date], ['period', (int) $period]])
            ->fetchAll();
        $marks = [];
        $updated = null;
        foreach ($rows as $r) {
            $marks[(int) $r->student_id] = (string) $r->status;
            if ($updated === null || $r->updated_at > $updated) {
                $updated = $r->updated_at;
            }
        }

        return [$marks, $updated];
    }

    /**
     * บันทึกสถานะของคาบ (เขียนทับของเดิม)
     *
     * @param array $marks [student_id => status] เฉพาะนักเรียนในรายวิชา
     */
    public static function save($course_id, $date, $period, array $marks, array $rooms, $teacher_id)
    {
        if (empty($marks)) {
            return;
        }
        $db = \Kotchasan\DB::create();
        $table = $db->getTableName('attendance');
        $now = date('Y-m-d H:i:s');
        $values = [];
        $params = [];
        foreach ($marks as $student_id => $status) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params, (int) $course_id, (int) ($rooms[$student_id] ?? 0), $date, (int) $period, (int) $student_id, $status, (int) $teacher_id, $now);
        }
        $db->raw('INSERT INTO `'.$table.'` (`course_id`, `room`, `date`, `period`, `student_id`, `status`, `teacher_id`, `updated_at`) VALUES '
            .implode(', ', $values)
            .' ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `room` = VALUES(`room`), `teacher_id` = VALUES(`teacher_id`), `updated_at` = VALUES(`updated_at`)', $params);
    }

    /**
     * สรุปเวลาเรียนของรายวิชา
     *
     * @return array [sessions, counts[student_id][status]]
     */
    public static function summary($course_id, $room)
    {
        $where = [['course_id', (int) $course_id]];
        if ($room > 0) {
            $where[] = ['room', (int) $room];
        }
        $rows = static::createQuery()
            ->select('student_id', 'status', 'date', 'period')
            ->from('attendance')
            ->where($where)
            ->fetchAll();
        $sessions = [];
        $counts = [];
        foreach ($rows as $r) {
            $sessions[$r->date.'#'.$r->period] = true;
            $sid = (int) $r->student_id;
            if (!isset($counts[$sid])) {
                $counts[$sid] = array_fill_keys(self::STATUSES, 0);
            }
            if (isset($counts[$sid][$r->status])) {
                $counts[$sid][$r->status]++;
            }
        }

        return [count($sessions), $counts];
    }
}

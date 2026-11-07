<?php
/**
 * @filesource modules/attendance/controllers/courses.php
 *
 * api/attendance/courses
 * รายวิชาของภาคเรียนปัจจุบันที่ผู้ใช้บันทึกเวลาเรียนได้ พร้อมห้องที่มีนักเรียนลงทะเบียน
 * ผู้จัดการนักเรียน/รายวิชา (และผู้ดูแล) เห็นทุกรายวิชา ครูเห็นเฉพาะรายวิชาที่ตัวเองสอน
 */

namespace Attendance\Courses;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            $year = (int) self::$cfg->academic_year;
            $term = (int) self::$cfg->term;
            $where = [['C.year', $year], ['C.term', $term], ['C.teacher_id', '>', 0]];
            $all = ApiController::hasPermission($login, ['can_manage_student', 'can_manage_course']);
            if (!$all) {
                if (!ApiController::hasPermission($login, ['can_teacher', 'can_rate_student'])) {
                    return $this->successResponse(['year' => $year, 'term' => $term, 'courses' => [], 'all' => false], 'OK');
                }
                $where[] = ['C.teacher_id', (int) $login->id];
            }
            $courses = \Kotchasan\Model::createQuery()
                ->select('C.id', 'C.course_code', 'C.course_name', 'C.class', 'C.credit', 'C.period', 'C.type', 'C.teacher_id', 'U.name teacher')
                ->from('course C')
                ->join('user U', [['U.id', 'C.teacher_id']], 'LEFT')
                ->where($where)
                ->orderBy('C.course_code')
                ->fetchAll();
            $category = \School\Category\Model::init();
            $ids = array_map(fn($c) => (int) $c->id, $courses);
            $rooms = [];
            if (!empty($ids)) {
                $rows = \Kotchasan\Model::createQuery()
                    ->select('course_id', 'room')
                    ->from('grade')
                    ->where([['course_id', $ids]])
                    ->groupBy('course_id')
                    ->groupBy('room')
                    ->fetchAll();
                foreach ($rows as $r) {
                    $rooms[(int) $r->course_id][] = ['id' => (int) $r->room, 'name' => (string) $category->get('room', $r->room, (string) $r->room)];
                }
            }
            $out = [];
            foreach ($courses as $c) {
                $out[] = [
                    'id' => (int) $c->id,
                    'code' => (string) $c->course_code,
                    'name' => (string) $c->course_name,
                    'class' => (string) $category->get('class', $c->class, ''),
                    'credit' => (float) $c->credit,
                    'period' => (int) $c->period,
                    'teacher' => (string) $c->teacher,
                    'rooms' => $rooms[(int) $c->id] ?? []
                ];
            }

            return $this->successResponse(['year' => $year, 'term' => $term, 'courses' => $out, 'all' => $all], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}

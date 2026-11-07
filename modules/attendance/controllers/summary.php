<?php
/**
 * @filesource modules/attendance/controllers/summary.php
 *
 * api/attendance/summary?course=&room=
 * สรุปเวลาเรียนรายวิชา: จำนวนคาบแต่ละสถานะ และร้อยละเวลาเรียน (มา + สาย) ต่อคาบที่บันทึกของนักเรียนคนนั้น
 */

namespace Attendance\Summary;

use Attendance\Sheet\Model;
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
            $course = \School\Course\Model::find($request->get('course')->toInt());
            if (!$course || !\School\Grades\Controller::canOpen($login, $course)) {
                return $this->errorResponse('Permission required', 403);
            }
            $room = $request->get('room')->toInt();
            Model::ensureTable();
            list($sessions, $counts) = Model::summary($course->id, $room);
            $category = \School\Category\Model::init();
            $rows = [];
            foreach (Model::students($course->id, $room) as $s) {
                $c = $counts[$s['id']] ?? array_fill_keys(Model::STATUSES, 0);
                $total = array_sum($c);
                $present = 0;
                foreach (Model::PRESENT as $k) {
                    $present += $c[$k];
                }
                $rows[] = $s + [
                    'room_name' => (string) $category->get('room', $s['room'], (string) $s['room']),
                    'counts' => $c,
                    'total' => $total,
                    'percent' => $total > 0 ? round($present * 100 / $total, 1) : null
                ];
            }

            return $this->successResponse([
                'course' => ['id' => (int) $course->id, 'code' => (string) $course->course_code, 'name' => (string) $course->course_name],
                'sessions' => $sessions,
                'students' => $rows
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}

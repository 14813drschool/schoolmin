<?php
/**
 * @filesource modules/attendance/controllers/sheet.php
 *
 * api/attendance/sheet?course=&room=&date=&period=   รายชื่อนักเรียนและสถานะของคาบ
 * api/attendance/sheet/save (POST)                    บันทึกสถานะของคาบ
 * สิทธิ์เดียวกับหน้าผลการเรียนของรายวิชา (School\Grades\Controller::canOpen)
 */

namespace Attendance\Sheet;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /** สิทธิ์ที่บันทึกเวลาเรียนได้ */
    const WRITE_PERMISSIONS = ['can_manage_student', 'can_manage_course', 'can_teacher', 'can_rate_student'];

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
            $date = self::date($request->get('date')->toString());
            $period = max(1, min(12, $request->get('period')->toInt()));
            Model::ensureTable();
            $students = Model::students($course->id, $request->get('room')->toInt());
            list($marks, $updated) = Model::marks($course->id, $date, $period);
            $category = \School\Category\Model::init();
            foreach ($students as $i => $s) {
                $students[$i]['room_name'] = (string) $category->get('room', $s['room'], (string) $s['room']);
                $students[$i]['status'] = $marks[$s['id']] ?? null;
            }

            return $this->successResponse([
                'course' => ['id' => (int) $course->id, 'code' => (string) $course->course_code, 'name' => (string) $course->course_name],
                'date' => $date,
                'period' => $period,
                'students' => $students,
                'updated_at' => $updated,
                'can_write' => (int) $login->active === 1 && ApiController::canModify($login, self::WRITE_PERMISSIONS)
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if ((int) $login->active !== 1 || !ApiController::canModify($login, self::WRITE_PERMISSIONS)) {
                return $this->errorResponse('Permission required', 403);
            }
            $course = \School\Course\Model::find($request->post('course')->toInt());
            if (!$course || !\School\Grades\Controller::canOpen($login, $course)) {
                return $this->errorResponse('Permission required', 403);
            }
            $date = self::date($request->post('date')->toString());
            $period = max(1, min(12, $request->post('period')->toInt()));
            $input = json_decode($request->post('marks')->toString(), true);
            if (!is_array($input) || empty($input)) {
                return $this->errorResponse('No data to save', 400);
            }
            Model::ensureTable();
            // รับเฉพาะนักเรียนที่ลงทะเบียนรายวิชานี้ และสถานะที่รู้จัก
            $rooms = [];
            foreach (Model::students($course->id, 0) as $s) {
                $rooms[$s['id']] = $s['room'];
            }
            $marks = [];
            foreach ($input as $sid => $status) {
                $sid = (int) $sid;
                if (isset($rooms[$sid]) && in_array($status, Model::STATUSES, true)) {
                    $marks[$sid] = $status;
                }
            }
            if (empty($marks)) {
                return $this->errorResponse('No data to save', 400);
            }
            Model::save($course->id, $date, $period, $marks, $rooms, $login->id);
            \Index\Log\Model::add((int) $course->id, 'attendance', 'Save', 'บันทึกเวลาเรียน '.$course->course_code.' '.$date.' คาบ '.$period.' ('.count($marks).' คน)', $login->id);

            return $this->successResponse(['saved' => count($marks), 'updated_at' => date('Y-m-d H:i:s')], 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * วันที่ YYYY-MM-DD ที่ถูกต้อง ไม่ใช่ก็ใช้วันนี้
     */
    private static function date($value)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $value;
        }

        return date('Y-m-d');
    }
}

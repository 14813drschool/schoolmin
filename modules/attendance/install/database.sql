-- ---------------------------------------------------------------------------
-- modules/attendance/install/database.sql — ตารางที่โมดูล attendance เป็นเจ้าของ
--
--   attendance  เวลาเรียนรายคาบ หนึ่งแถวต่อนักเรียน ต่อรายวิชา ต่อวัน ต่อคาบ
--               student_id = user.id ของนักเรียน (เหมือน grade.student_id)
--               room       = ห้องของนักเรียนในรายวิชานั้น (grade.room)
--               status     = present | late | leave | sick | absent
-- ไซต์ที่ติดตั้งไว้ก่อนมีโมดูลนี้ ตารางจะถูกสร้างเองเมื่อเรียกใช้ครั้งแรก (Attendance\Sheet\Model::ensureTable)
-- ---------------------------------------------------------------------------

CREATE TABLE `{prefix}_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `room` int(11) NOT NULL DEFAULT 0,
  `date` date NOT NULL,
  `period` tinyint(2) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` varchar(10) NOT NULL,
  `teacher_id` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sheet` (`course_id`,`date`,`period`,`student_id`),
  KEY `course_room` (`course_id`,`room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

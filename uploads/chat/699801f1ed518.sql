-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 20, 2026 at 04:36 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u957189082_workack`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `priority` varchar(50) NOT NULL DEFAULT 'Medium',
  `publish_date` date NOT NULL,
  `message` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `target_audience` varchar(50) DEFAULT 'All',
  `is_pinned` tinyint(1) DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `category`, `priority`, `publish_date`, `message`, `created_by`, `is_archived`, `created_at`, `image_path`, `target_audience`, `is_pinned`, `attachment_path`) VALUES
(2, 'Office Annual day Celebration', 'Event', 'Medium', '2026-02-18', 'Hi Everyone,\r\n\r\nGet ready to put your work aside because it’s time to celebrate! We are thrilled to announce our Annual Day celebration for [Year].\r\n\r\nThis past year was filled with challenges, growth, and some massive wins—and we couldn\'t have done it without every single one of you. Now, it’s time to let loose, enjoy some great food, and dance the night away!', 11, 0, '2026-02-18 05:31:54', NULL, 'All', 0, NULL),
(9, 'dfsg', 'Holiday', 'Low', '2026-02-22', 'sunday', 11, 0, '2026-02-18 05:36:03', NULL, 'All', 0, NULL),
(10, 'pongal holiday', 'Holiday', 'Medium', '2026-02-27', 'office will be closed for pongal leave from jan 14 to jan 17', 11, 0, '2026-02-18 05:46:48', 'uploads/69955247ae7d1.jpg', 'All', 0, NULL),
(11, 'Diwali  leave', 'General', 'Medium', '2026-02-19', 'wsfdbxdndgn', 11, 0, '2026-02-18 06:42:11', 'uploads/images/69955f4334d8b.png', 'All', 0, 'uploads/docs/69955f4335abd.pdf'),
(12, 'Summer vacation leave', 'Holiday', 'Medium', '2026-02-19', 'nsjfhugvhbkj', 16, 0, '2026-02-19 11:21:52', 'uploads/images/6996f24fec7fc.jpg', 'Team Lead', 0, 'uploads/docs/6996f24fed42f.pdf'),
(13, 'summer leave', 'Holiday', 'Medium', '2026-02-21', 'dsvdv', 11, 0, '2026-02-19 11:27:31', 'uploads/images/6996f3a31da84.jpg', 'All', 0, 'uploads/docs/6996f3a31e177.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `punch_in` datetime DEFAULT NULL,
  `punch_out` datetime DEFAULT NULL,
  `production_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('On Time','Late','WFH','Absent') DEFAULT 'On Time',
  `date` date NOT NULL,
  `break_time` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `department`, `punch_in`, `punch_out`, `production_hours`, `status`, `date`, `break_time`) VALUES
(6, 8, NULL, '2026-02-17 13:57:35', NULL, 0.00, 'On Time', '2026-02-17', NULL),
(8, 5, NULL, '2026-02-18 15:46:00', NULL, 0.00, 'On Time', '2026-02-18', NULL),
(9, 5, NULL, '2026-02-19 10:46:42', '2026-02-19 18:27:42', 0.01, 'On Time', '2026-02-19', NULL),
(10, 4, NULL, '2026-02-19 06:27:00', '2026-02-19 06:37:50', 0.17, 'On Time', '2026-02-19', NULL),
(14, 19, NULL, '2026-02-19 15:18:39', NULL, 0.00, 'On Time', '2026-02-19', NULL),
(15, 24, NULL, '2026-02-19 16:10:19', NULL, 0.00, 'On Time', '2026-02-19', NULL),
(16, 15, NULL, '2026-02-19 16:18:35', NULL, 0.00, 'On Time', '2026-02-19', NULL),
(19, 22, NULL, '2026-02-19 18:39:30', NULL, 0.00, 'On Time', '2026-02-19', NULL),
(20, 22, NULL, '2026-02-20 09:49:07', '2026-02-20 09:58:25', 0.14, 'On Time', '2026-02-20', '1');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_breaks`
--

CREATE TABLE `attendance_breaks` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `break_start` datetime NOT NULL,
  `break_end` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `attendance_breaks`
--

INSERT INTO `attendance_breaks` (`id`, `attendance_id`, `break_start`, `break_end`) VALUES
(9, 7, '2026-02-18 15:36:49', '2026-02-18 15:37:12'),
(10, 7, '2026-02-18 15:37:30', '2026-02-18 15:37:47'),
(11, 8, '2026-02-18 15:46:19', '2026-02-18 15:53:37'),
(12, 8, '2026-02-18 15:53:55', '2026-02-18 18:39:43'),
(13, 8, '2026-02-18 15:53:57', '2026-02-18 18:39:43'),
(14, 9, '2026-02-19 10:47:02', '2026-02-19 18:27:23'),
(15, 10, '2026-02-19 06:28:20', '2026-02-19 06:28:30'),
(16, 10, '2026-02-19 06:36:17', '2026-02-19 06:36:24'),
(17, 10, '2026-02-19 06:37:31', '2026-02-19 06:37:41'),
(18, 14, '2026-02-19 15:18:51', '2026-02-19 16:40:28'),
(19, 15, '2026-02-19 16:10:37', '2026-02-19 16:11:03'),
(20, 16, '2026-02-19 16:18:43', '2026-02-19 16:18:52'),
(21, 16, '2026-02-19 16:18:44', '2026-02-19 16:18:52'),
(22, 17, '2026-02-19 18:11:40', '2026-02-19 18:12:43'),
(23, 18, '2026-02-19 18:24:01', '2026-02-19 18:25:59'),
(24, 20, '2026-02-20 09:56:01', '2026-02-20 09:56:57');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `candidate_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `applied_role` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `resume_path` varchar(255) NOT NULL,
  `skills` text DEFAULT NULL,
  `match_score` int(11) DEFAULT 0,
  `status` enum('Parsed','Shortlisted','Rejected') DEFAULT 'Parsed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `candidate_id`, `name`, `email`, `applied_role`, `phone`, `resume_path`, `skills`, `match_score`, `status`, `created_at`) VALUES
(7, 'Cand-61971', 'Varshini Arunachalam', 'varshinia444@gmail.com', 'Applied Candidate', '+91 9944901079', '../uploads/resumes/1771494937_VARSHINI_RESUME.pdf', 'Sql, Html, Css, Python, Java, Excel', 53, 'Parsed', '2026-02-19 09:58:31'),
(8, 'Cand-42025', 'Srinidhi Nivashini Ramesh', 'srinidhinivashini09@gmail.com', 'Applied Candidate', '9361078921', '../uploads/resumes/1771495115_Srinidhi_1.pdf', 'Sql, Html, Css, Java', 43, 'Parsed', '2026-02-19 09:59:46'),
(9, 'Cand-70593', 'Aparna M A', 'aparnaabii2003@gmail.com', 'Applied Candidate', '+91 6385500896', '../uploads/resumes/1771495190_resume_Msc__2_.pdf', 'Sql, Html, Css, Python, Java, Excel', 58, 'Parsed', '2026-02-19 10:00:30');

-- --------------------------------------------------------

--
-- Table structure for table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `type` enum('direct','group') DEFAULT 'direct',
  `group_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `chat_conversations`
--

INSERT INTO `chat_conversations` (`id`, `type`, `group_name`, `created_at`, `created_by`) VALUES
(1, 'direct', NULL, '2026-02-18 07:02:04', NULL),
(2, 'direct', NULL, '2026-02-18 07:14:17', NULL),
(3, 'direct', NULL, '2026-02-18 14:35:33', NULL),
(4, 'direct', NULL, '2026-02-19 10:42:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `message_type` enum('text','image','file','call') DEFAULT 'text',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `conversation_id`, `sender_id`, `message`, `attachment_path`, `message_type`, `is_read`, `created_at`) VALUES
(1, 1, 3, 'helo', NULL, 'text', 1, '2026-02-18 07:02:39'),
(2, 1, 3, 'hellooooo', NULL, 'text', 1, '2026-02-18 07:02:51'),
(3, 4, 19, 'hii', NULL, 'text', 0, '2026-02-19 11:40:36'),
(4, 4, 19, 'https://meet.jit.si/SmartHR-wyo8l9', NULL, 'call', 0, '2026-02-19 11:41:06'),
(5, 4, 19, 'https://meet.jit.si/SmartHR-a71nfo', NULL, 'call', 0, '2026-02-19 11:41:31');

-- --------------------------------------------------------

--
-- Table structure for table `chat_participants`
--

CREATE TABLE `chat_participants` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `chat_participants`
--

INSERT INTO `chat_participants` (`id`, `conversation_id`, `user_id`, `joined_at`) VALUES
(1, 1, 3, '2026-02-18 07:02:04'),
(2, 1, 19, '2026-02-18 07:02:05'),
(3, 2, 3, '2026-02-18 07:14:17'),
(4, 2, 20, '2026-02-18 07:14:18'),
(5, 3, 5, '2026-02-18 14:35:33'),
(6, 3, 19, '2026-02-18 14:35:34'),
(7, 4, 24, '2026-02-19 10:42:56'),
(8, 4, 19, '2026-02-19 10:42:56');

-- --------------------------------------------------------

--
-- Table structure for table `daily_metrics`
--

CREATE TABLE `daily_metrics` (
  `id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `page_views` int(11) DEFAULT 0,
  `conversions` int(11) DEFAULT 0,
  `revenue` decimal(10,2) DEFAULT 0.00,
  `active_users` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `dept` varchar(100) NOT NULL,
  `company` varchar(150) NOT NULL,
  `join_date` date NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  `img` varchar(255) DEFAULT NULL,
  `emp_type` varchar(50) DEFAULT NULL,
  `pan` varchar(50) DEFAULT NULL,
  `pf_no` varchar(50) DEFAULT NULL,
  `esi_no` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_no` varchar(50) DEFAULT NULL,
  `ifsc` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `email`, `phone`, `designation`, `dept`, `company`, `join_date`, `status`, `img`, `emp_type`, `pan`, `pf_no`, `esi_no`, `bank_name`, `account_no`, `ifsc`) VALUES
('EMP-001', 'Anthony', 'Lewis', 'anthonyyyyyyy@example.com', '(123) 4567 890', 'Team Lead', 'Finance', 'Abac Company', '2024-09-12', 'Active', '11', 'Permanent', 'ABCDE1234F', 'PF101010', 'ESI202020', 'HDFC Bank', '1234567890', 'HDFC000123'),
('EMP-002', 'Brian', 'Villalobos', 'brian@example.com', '(179) 7382 829', 'Senior Developer', 'Development', 'Abac Company', '2024-10-24', 'Active', '12', 'Contract', 'FGHIJ5678K', 'PF303030', 'ESI404040', 'SBI', '0987654321', 'SBIN000456'),
('EMP-003', 'Harvey', 'Smith', 'harvey@example.com', '(782) 8291 920', 'Team Lead', 'Sales', 'Abac Company', '2025-02-15', 'Inactive', '13', 'Permanent', '', '', '', '', '', ''),
('EMP-004', 'Stephan', 'Peralt', 'stephan@example.com', '(929) 1022 222', 'Android Developer', 'Development', 'Abac Company', '2025-03-01', 'Active', '14', 'Intern', '', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `employee_onboarding`
--

CREATE TABLE `employee_onboarding` (
  `id` int(11) NOT NULL,
  `emp_id_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `salary` varchar(50) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT 'Permanent',
  `joining_date` date NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `pan_no` varchar(20) DEFAULT NULL,
  `pf_no` varchar(50) DEFAULT NULL,
  `esi_no` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_acc_no` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `profile_img` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `employee_onboarding`
--

INSERT INTO `employee_onboarding` (`id`, `emp_id_code`, `first_name`, `last_name`, `email`, `phone`, `department`, `designation`, `manager_name`, `salary`, `employment_type`, `joining_date`, `username`, `password_hash`, `pan_no`, `pf_no`, `esi_no`, `bank_name`, `bank_acc_no`, `ifsc_code`, `permissions`, `status`, `profile_img`, `created_at`) VALUES
(1, 'EMP-006', 'Varshini', 'A', 'varshiniemp@gmail.com', '9944901079', 'Development Team', 'Junior Developer', 'Charlie Davis', '250000', 'Intern', '2026-02-19', 'Varshinideveloper', '$2y$10$4ikv7Zu9ynZiKlSqJtWmx.hiPNPCC1luJKuOmtVre4326l3NA1TXG', 'ASDDF1234A', 'AS12345678', 'ESI987654', 'HDFC', '1234567890', 'HDFC0001234', NULL, 'Completed', 'https://ui-avatars.com/api/?name=Varshini+A&background=random', '2026-02-19 06:40:45'),
(2, 'EMP-007', 'Aparna', 'M A', 'aparnaemp@gmail.com', '9876543210', 'Development Team', 'Junior Developer', 'Charlie Davis', '30000', 'Permanent', '2026-02-12', 'aparnadeveloper', '$2y$10$Xfv825pBQPW.akprf.7jq.MtpNilWehsxAKHtQ9WsRumdKrHt.wSy', 'ASDDF1234A', 'AS12345678', 'ESI987654', 'HDFC', '1234567890', 'HDFC0001234', NULL, 'Completed', 'https://ui-avatars.com/api/?name=Aparna+M+A&background=random', '2026-02-19 06:51:27');

-- --------------------------------------------------------

--
-- Table structure for table `employee_performance`
--

CREATE TABLE `employee_performance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_score` decimal(5,1) DEFAULT 0.0,
  `performance_grade` varchar(50) DEFAULT 'Pending',
  `project_completion_pct` int(3) DEFAULT 0,
  `project_details` varchar(100) DEFAULT '0/0 On Time',
  `task_completion_pct` int(3) DEFAULT 0,
  `task_details` varchar(100) DEFAULT '0 Completed',
  `total_tasks_assigned` int(11) DEFAULT 0,
  `completed_on_time` int(11) DEFAULT 0,
  `overdue_tasks` int(11) DEFAULT 0,
  `attendance_pct` int(3) DEFAULT 0,
  `attendance_details` varchar(100) DEFAULT '0 Days Leave',
  `manager_rating_pct` int(3) DEFAULT 0,
  `manager_details` varchar(100) DEFAULT 'Soft Skills',
  `manager_comments` text DEFAULT NULL,
  `project_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`project_history`)),
  `weekly_trend` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weekly_trend`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `employee_performance`
--

INSERT INTO `employee_performance` (`id`, `user_id`, `total_score`, `performance_grade`, `project_completion_pct`, `project_details`, `task_completion_pct`, `task_details`, `total_tasks_assigned`, `completed_on_time`, `overdue_tasks`, `attendance_pct`, `attendance_details`, `manager_rating_pct`, `manager_details`, `manager_comments`, `project_history`, `weekly_trend`) VALUES
(1, 1, 98.0, 'Excellent', 0, '0/0 On Time', 0, '0 Completed', 0, 0, 0, 0, '0 Days Leave', 0, 'Soft Skills', NULL, NULL, NULL),
(2, 22, 1.0, 'High', 2, '0/0 On Time', 3, '0 Completed', 0, 0, 0, 4, '0 Days Leave', 0, 'Soft Skills', NULL, NULL, NULL),
(3, 5, 76.0, 'Average', 88, '0/0 On Time', 91, '0 Completed', 0, 0, 0, 40, '0 Days Leave', 0, 'Soft Skills', NULL, NULL, NULL),
(4, 23, 11.0, 'Low', 22, '0/0 On Time', 33, '0 Completed', 0, 0, 0, 44, '0 Days Leave', 0, 'Soft Skills', NULL, NULL, NULL),
(5, 19, 88.0, 'Excellent', 88, '0/0 On Time', 88, '0 Completed', 0, 0, 0, 88, '0 Days Leave', 0, 'Soft Skills', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT 'Engineering Dept',
  `reporting_to` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `experience_label` varchar(50) DEFAULT 'Fresher',
  `emp_id_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `marital_status` varchar(20) DEFAULT 'Single',
  `nationality` varchar(50) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `profile_img` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `emergency_contacts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`emergency_contacts`)),
  `family_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`family_info`)),
  `experience_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`experience_history`)),
  `education_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education_history`)),
  `bank_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bank_info`)),
  `storage_used_gb` decimal(10,2) DEFAULT 45.50,
  `storage_limit_gb` decimal(10,2) DEFAULT 100.00,
  `storage_docs_gb` decimal(10,2) DEFAULT 20.20,
  `storage_media_gb` decimal(10,2) DEFAULT 15.10,
  `storage_system_gb` decimal(10,2) DEFAULT 10.20,
  `last_password_change` timestamp NULL DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`id`, `user_id`, `full_name`, `designation`, `department`, `reporting_to`, `manager_id`, `experience_label`, `emp_id_code`, `phone`, `location`, `dob`, `gender`, `marital_status`, `nationality`, `joining_date`, `profile_img`, `email`, `emergency_contacts`, `family_info`, `experience_history`, `education_history`, `bank_info`, `storage_used_gb`, `storage_limit_gb`, `storage_docs_gb`, `storage_media_gb`, `storage_system_gb`, `last_password_change`, `status`) VALUES
(1, 1, 'System Administratorrrr', 'System Admin', 'Management', NULL, NULL, 'Fresher', 'EMP-ADM01', NULL, NULL, NULL, 'Male', 'Single', NULL, '2023-01-01', NULL, 'adminnnn@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(2, 14, 'Alice Sharmaaaaa', 'HR Manager', 'Human Resources', NULL, NULL, 'Fresher', 'EMP-HR01', NULL, NULL, NULL, 'Female', 'Single', NULL, '2023-04-10', NULL, 'aliceeee.hr@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(3, 15, 'Bob Johnson', 'HR Executive', 'Human Resources', NULL, NULL, 'Fresher', 'EMP-HRE01', NULL, NULL, NULL, 'Male', 'Single', NULL, '2023-06-01', NULL, 'bob.hrexec@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(4, 16, 'Charlie Davis', 'Project Manager', 'Development Team', NULL, NULL, 'Fresher', 'EMP-MGR01', NULL, NULL, NULL, 'Male', 'Married', NULL, '2023-03-15', NULL, 'charlie.mgr@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(5, 19, 'Frank Castle', 'Technical Team Lead', 'Development Team', NULL, 16, 'Fresher', 'EMP-TL01', NULL, NULL, NULL, 'Male', 'Married', NULL, '2023-05-20', NULL, 'frank.tl@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(6, 5, 'Stephen Peralt', 'Senior Software Engineer', 'Development Team', NULL, NULL, 'Fresher', 'EMP-005', NULL, NULL, NULL, 'Male', 'Single', NULL, '2024-01-15', NULL, 'employee@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(7, 20, 'Grace Lee', 'Frontend Developer', 'Development Team', 19, 16, 'Fresher', 'EMP-E01', NULL, NULL, NULL, 'Female', 'Single', NULL, '2024-02-10', NULL, 'grace.emp@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(8, 21, 'Henry Ford', 'Backend Developer', 'Development Team', NULL, NULL, 'Fresher', 'EMP-E02', NULL, NULL, NULL, 'Male', 'Single', NULL, '2024-03-01', NULL, 'henry.emp@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(9, 9, 'IT Admin Head', 'IT Manager', 'IT Infrastructure', NULL, NULL, 'Fresher', 'EMP-IT01', NULL, NULL, NULL, 'Male', 'Single', NULL, '2023-02-15', NULL, 'itadmin@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(10, 10, 'IT Executive Support', 'Systems Engineer', 'IT Infrastructure', NULL, NULL, 'Fresher', 'EMP-ITE01', NULL, NULL, NULL, 'Male', 'Single', NULL, '2023-08-11', NULL, 'itexecutive@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(11, 6, 'Sales Head', 'VP of Sales', 'Sales & Marketing', NULL, NULL, 'Fresher', 'EMP-SLS01', NULL, NULL, NULL, 'Male', 'Married', NULL, '2023-01-20', NULL, 'sales@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(12, 7, 'Digital Marketing Lead', 'Marketing Manager', 'Sales & Marketing', NULL, NULL, 'Fresher', 'EMP-DM01', NULL, NULL, NULL, 'Female', 'Single', NULL, '2023-07-22', NULL, 'dm@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(13, 17, 'Diana Prince', 'Chief Financial Officer (CFO)', 'Finance & Accounts', NULL, NULL, 'Fresher', 'EMP-CFO01', NULL, NULL, NULL, 'Female', 'Married', NULL, '2023-01-10', NULL, 'diana.cfo@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(14, 18, 'Evan Wright', 'Senior Accountant', 'Finance & Accounts', NULL, NULL, 'Fresher', 'EMP-ACC01', NULL, NULL, NULL, 'Male', 'Single', NULL, '2023-09-05', NULL, 'evan.accounts@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(15, 24, 'Aparna M A', 'Junior Developer', 'Development Team', 16, 16, 'Fresher', 'EMP-007', '9876543210', NULL, NULL, 'Male', 'Single', NULL, '2026-02-12', 'https://ui-avatars.com/api/?name=Aparna+M+A&background=random', 'aparnaemp@gmail.com', NULL, NULL, NULL, NULL, '{\"bank_name\":\"HDFC\",\"acc_no\":\"1234567890\",\"ifsc\":\"HDFC0001234\",\"pan\":\"ASDDF1234A\",\"pf_no\":\"AS12345678\",\"esi_no\":\"ESI987654\"}', 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(16, 22, 'Brian Villalobos', 'Senior Developer', 'Development Team', 19, 16, 'Fresher', NULL, '', '', '0000-00-00', 'Male', 'Single', '', NULL, 'user_22_1771504439.jpg', '', NULL, NULL, NULL, NULL, '{\"bank_name\":\"hdfc\",\"acc_no\":\"3456765432345678\",\"ifsc\":\"hdfc0001234\",\"pan\":\"ASDSE0987f\"}', 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(17, 23, 'Julia Gomes', 'UI Designer', 'Development Team', 19, 16, 'Fresher', NULL, NULL, NULL, NULL, 'Male', 'Single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(18, 17, 'Anthony Lewis', 'Team Lead', 'Finance', NULL, NULL, 'Experienced', 'EMP-001', NULL, NULL, NULL, 'Male', 'Single', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(44, NULL, 'premkar', 'Project Manager', 'BCA', NULL, NULL, 'Fresher', 'EMP-8569', '989898989', NULL, NULL, 'Male', 'Single', NULL, '2026-02-20', NULL, 'premkarthik102005@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(45, NULL, 'premk', 'Project Manager', 'BCA', NULL, NULL, 'Fresher', 'EMP-5741', '989898989', NULL, NULL, 'Male', 'Single', NULL, '2026-02-20', NULL, 'premkarthik102005@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active'),
(46, NULL, 'Premkarthik M J', 'Project Manager', 'BCA', NULL, NULL, 'Fresher', 'EMP-9784', '989898989', NULL, NULL, 'Male', 'Single', NULL, '2026-02-20', NULL, 'premkarthik102005@gmail.com', NULL, NULL, NULL, NULL, NULL, 45.50, 100.00, 20.20, 15.10, 10.20, NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `skill_name` varchar(50) NOT NULL,
  `proficiency` int(11) NOT NULL,
  `color_hex` varchar(10) DEFAULT '#0d9488',
  `last_updated` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `employee_skills`
--

INSERT INTO `employee_skills` (`id`, `user_id`, `skill_name`, `proficiency`, `color_hex`, `last_updated`) VALUES
(4, 5, 'Figma', 95, '#f97316', '2026-02-17'),
(5, 5, 'HTML5', 85, '#22c55e', '2026-02-17');

-- --------------------------------------------------------

--
-- Table structure for table `help_articles`
--

CREATE TABLE `help_articles` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `help_articles`
--

INSERT INTO `help_articles` (`id`, `category_id`, `title`, `content`, `created_at`) VALUES
(1, 1, 'What is an HRMS and Why is it Essential?', 'An HRMS (Human Resource Management System) is a suite of software used to manage internal HR functions. From employee data to payroll, recruitment, and benefits, it centralizes all employee information in one secure location.', '2026-02-18 13:29:44'),
(2, 1, 'The Key Features of an HRMS Explained', 'Key features include Employee Information Management, Payroll processing, Time and Attendance tracking, Recruitment/ATS, and Performance Management systems.', '2026-02-18 13:29:44'),
(3, 1, 'How HRMS Helps Automate HR Tasks', 'Automation reduces manual entry in payroll, tracks leave balances automatically, and sends notifications for document expirations or performance reviews.', '2026-02-18 13:29:44'),
(4, 2, 'How to view & update your personal profile', 'Navigate to the Profile section from the sidebar. Click Edit, update your contact details or address, and click Save. Some changes may require HR approval.', '2026-02-18 13:29:44'),
(5, 2, 'Steps to Apply for Leave via the Portal', '1. Go to Leave Management. 2. Select Apply Leave. 3. Choose Leave Type (Sick/Annual). 4. Pick dates and submit for Manager approval.', '2026-02-18 13:29:44'),
(6, 2, 'How to access and download your payslips', 'Visit the Payroll module. Select Payslips. Choose the specific month and year, then click Download PDF to save it to your device.', '2026-02-18 13:29:44'),
(7, 2, 'Resetting your password securely', 'Click Forgot Password on the login screen. An OTP will be sent to your registered email. Enter the OTP and create a new password following the complexity rules.', '2026-02-18 13:29:44'),
(8, 3, 'How to Approve or Reject Employee Requests', 'As a manager, go to your Inbox or Approval Center. Review the request details and click Approve or Reject with an optional comment.', '2026-02-18 13:29:44'),
(9, 4, 'Understanding your Salary Structure', 'Your salary is composed of Basic Pay, HRA, Special Allowance, and Deductions like PF and Professional Tax. Details are found in your digital contract.', '2026-02-18 13:29:44'),
(10, 5, 'How to use the Biometric Punch System', 'Ensure your fingerprint or face is registered. Simply scan at the entrance/exit. The data syncs to the HRMS every 30 minutes.', '2026-02-18 13:29:44'),
(11, 6, 'Leave Types and Entitlements', 'Employees are entitled to 12 Sick Leaves, 15 Annual Leaves, and Public Holidays as per the company calendar. Check your balance in the Leave module.', '2026-02-18 13:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `help_categories`
--

CREATE TABLE `help_categories` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `help_categories`
--

INSERT INTO `help_categories` (`id`, `title`, `created_at`) VALUES
(1, 'Introduction to HRMS', '2026-02-18 13:29:44'),
(2, 'Employee Self-Service (ESS)', '2026-02-18 13:29:44'),
(3, 'Manager Self-Service (MSS)', '2026-02-18 13:29:44'),
(4, 'Payroll Management', '2026-02-18 13:29:44'),
(5, 'Attendance & Time Tracking', '2026-02-18 13:29:44'),
(6, 'Leave Management', '2026-02-18 13:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `hiring_requests`
--

CREATE TABLE `hiring_requests` (
  `id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `job_title` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `vacancy_count` int(11) NOT NULL DEFAULT 1,
  `experience_required` varchar(100) NOT NULL,
  `skills_required` text NOT NULL,
  `job_description` text NOT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('Pending','Approved','In Progress','Fulfilled','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `hiring_requests`
--

INSERT INTO `hiring_requests` (`id`, `manager_id`, `job_title`, `department`, `vacancy_count`, `experience_required`, `skills_required`, `job_description`, `priority`, `status`, `created_at`) VALUES
(1, 3, 'java developer', 'Engineering', 1, 'Fresher', 'java,html, css', 'need a fresher ', 'High', 'Pending', '2026-02-18 11:28:08');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `requested_by` varchar(255) NOT NULL,
  `loc` varchar(255) NOT NULL,
  `sal` varchar(255) NOT NULL,
  `exp` varchar(100) NOT NULL,
  `icon` varchar(100) NOT NULL,
  `icon_bg` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `title`, `requested_by`, `loc`, `sal`, `exp`, `icon`, `icon_bg`, `created_at`) VALUES
(1, 'Senior IOS Developer', 'Sarah Jenkins', 'New York, USA', '30, 000 - 35, 000 / month', '1 years', 'fa-apple', 'bg-gray-50', '2026-02-19 07:05:44'),
(2, 'Junior PHP Developer', 'Michael Chen', 'Los Angeles, USA', '20, 000 - 25, 000 / month', '4 years', 'fa-php', 'bg-blue-50', '2026-02-19 07:05:44'),
(3, 'Network Engineer', 'David Ross', 'Bristol, UK', '30, 000 - 35, 000 / month', '1 year', 'fa-globe', 'bg-gray-50', '2026-02-19 07:05:44'),
(4, 'React Developer', 'Elena Rodriguez', 'Birmingham, UK', '28, 000 - 32, 000 / month', '3 years', 'fa-react', 'bg-blue-50', '2026-02-19 07:05:44'),
(5, 'Laravel Developer', 'James Wilson', 'Washington, USA', '30, 000 - 35, 000 / month', '2 years', 'fa-laravel', 'bg-red-50', '2026-02-19 07:05:44'),
(6, 'DevOps Engineer', 'Amina Okafor', 'Coventry, UK', '30, 000 - 35, 000 / month', '2 years', 'fa-gears', 'bg-gray-50', '2026-02-19 07:05:44'),
(7, 'Android Developer', 'Robert Pike', 'Chicago, USA', '30, 000 - 35, 000 / month', '2 years', 'fa-android', 'bg-green-50', '2026-02-19 07:05:44'),
(8, 'HTML Developer', 'Sophie Turner', 'Carlisle, UK', '30, 000 - 35, 000 / month', '2 years', 'fa-html5', 'bg-orange-50', '2026-02-18 07:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('Sick','Casual','Loss of Pay') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `total_days` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `tl_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `leave_type` enum('Annual','Medical','Casual','Other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `tl_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `manager_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `hr_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `user_id`, `department`, `tl_id`, `manager_id`, `leave_type`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `tl_status`, `manager_status`, `hr_status`, `approved_by`, `created_at`) VALUES
(1, 1, NULL, NULL, NULL, 'Medical', '2026-02-05', '2026-02-06', 2, 'Fever', 'Approved', 'Pending', 'Pending', 'Pending', NULL, '2026-02-17 09:03:45'),
(2, 5, NULL, NULL, NULL, 'Medical', '2026-02-18', '2026-02-19', 2, 'med', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-17 13:20:10'),
(9, 20, NULL, NULL, NULL, 'Casual', '2026-02-20', '2026-02-20', 1, 'cgfghhhhhhhvgt', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 07:12:44'),
(10, 20, NULL, 19, NULL, 'Medical', '2026-02-20', '2026-02-21', 2, 'Medical emergency ', 'Pending', 'Approved', 'Pending', 'Pending', NULL, '2026-02-19 07:26:09'),
(11, 20, NULL, 19, NULL, 'Other', '2026-02-20', '2026-02-21', 2, 'dsgrsefdv', 'Approved', 'Approved', 'Pending', 'Pending', NULL, '2026-02-19 07:36:52'),
(12, 22, NULL, NULL, NULL, 'Medical', '2026-02-20', '2026-02-21', 2, 'emergency ', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 09:37:36'),
(13, 22, NULL, 19, NULL, 'Casual', '2026-02-20', '2026-02-21', 2, 'family emergency ', 'Rejected', 'Rejected', 'Pending', 'Pending', NULL, '2026-02-19 10:25:44'),
(14, 24, NULL, 16, 16, 'Medical', '2026-02-19', '2026-02-26', 8, 'Teset', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 10:49:20'),
(15, 22, NULL, 19, NULL, 'Medical', '2026-02-19', '2026-02-28', 10, 'fever', 'Approved', 'Approved', 'Pending', 'Pending', NULL, '2026-02-19 11:01:30'),
(16, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:15'),
(17, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:22'),
(18, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:26'),
(19, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:30'),
(20, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:33'),
(21, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:37'),
(22, 5, NULL, NULL, NULL, 'Annual', '2026-02-20', '2026-02-21', 2, 'efWEF', 'Pending', 'Pending', 'Pending', 'Pending', NULL, '2026-02-19 13:00:41');

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT 'General',
  `meeting_time` time NOT NULL,
  `meeting_date` date NOT NULL,
  `type_color` enum('orange','teal','yellow','green') DEFAULT 'teal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `title`, `department`, `meeting_time`, `meeting_date`, `type_color`) VALUES
(1, 'Marketing Strategy', 'Marketing', '09:25:00', '2026-02-17', 'orange'),
(2, 'Design Review', 'Design Team', '11:20:00', '2026-02-17', 'teal'),
(3, 'Birthday Celebration', 'HR', '14:18:00', '2026-02-17', 'yellow'),
(4, 'Marketing Strategy', 'Marketing', '09:25:00', '2026-02-17', 'orange'),
(5, 'Design Review', 'Design Team', '11:20:00', '2026-02-17', 'teal'),
(6, 'Birthday Celebration', 'HR', '14:15:00', '2026-02-17', 'yellow');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `type` enum('file','comment','alert') DEFAULT 'alert',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `created_at`) VALUES
(1, 1, 'Lex Murphy requested access', 'UNIX Directory Access', 'file', '2026-02-17 09:33:10'),
(2, 1, 'John Doe commented', 'On your task #402', 'comment', '2026-02-17 09:33:10'),
(3, 1, 'Admin requested leave', 'Leave Approval Pending', 'alert', '2026-02-17 09:33:10'),
(4, 1, 'Lex Murphy requested access', 'UNIX Directory Access', 'file', '2026-02-17 09:42:23'),
(5, 1, 'John Doe commented', 'On your task #402', 'comment', '2026-02-17 09:42:23'),
(6, 1, 'Admin requested leave', 'Leave Approval Pending', 'alert', '2026-02-17 09:42:23'),
(7, 1, 'New Ticket: TKT-45247', 'Dept: IT Support | Priority: High', 'alert', '2026-02-19 12:17:55'),
(8, 9, 'New Ticket: TKT-45247', 'Dept: IT Support | Priority: High', 'alert', '2026-02-19 12:17:55'),
(10, 1, 'New Ticket: TKT-48241', 'Dept: IT Support | Priority: High', 'alert', '2026-02-19 12:29:22'),
(11, 9, 'New Ticket: TKT-48241', 'Dept: IT Support | Priority: High', 'alert', '2026-02-19 12:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `payslip_requests`
--

CREATE TABLE `payslip_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `request_id` varchar(20) NOT NULL,
  `requested_date` timestamp NULL DEFAULT current_timestamp(),
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `accounts_reply` text DEFAULT '-',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `payslip_requests`
--

INSERT INTO `payslip_requests` (`id`, `user_id`, `department`, `request_id`, `requested_date`, `from_date`, `to_date`, `priority`, `status`, `accounts_reply`, `note`) VALUES
(1, 5, NULL, 'REQ-292', '2026-02-17 12:43:47', '2026-02-18', '2026-02-20', 'High', 'Pending', '-', 'need payslip'),
(2, 5, NULL, 'REQ-377', '2026-02-17 13:24:24', '2026-02-19', '2026-02-20', 'Medium', 'Pending', '-', 'test'),
(3, 4, NULL, 'REQ-69940A', '2026-02-18 11:05:47', '2026-02-01', '2026-02-18', 'High', 'Pending', '-', 'ITR filing'),
(4, 22, NULL, 'REQ-716', '2026-02-19 11:19:39', '2026-02-01', '2026-02-19', 'High', 'Pending', '-', 'ITR filing');

-- --------------------------------------------------------

--
-- Table structure for table `personal_targets`
--

CREATE TABLE `personal_targets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `target_date` date NOT NULL,
  `target_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_taskboard`
--

CREATE TABLE `personal_taskboard` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `due_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('todo','inprogress','completed') DEFAULT 'todo',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `personal_taskboard`
--

INSERT INTO `personal_taskboard` (`id`, `user_id`, `title`, `priority`, `due_date`, `description`, `status`, `created_at`) VALUES
(1, 5, 'ui', 'High', '2026-02-17', 'complete the task', 'completed', '2026-02-17 09:16:34'),
(2, 1, 'Patient appointment booking', 'High', '0000-00-00', NULL, 'todo', '2026-02-17 09:33:10'),
(3, 1, 'Payment Gateway Integration', 'Medium', '0000-00-00', NULL, 'inprogress', '2026-02-17 09:33:10'),
(4, 1, 'Video Conferencing Module', 'High', '0000-00-00', NULL, 'completed', '2026-02-17 09:33:10'),
(5, 5, 'test', 'High', '2026-02-20', 'test', 'completed', '2026-02-17 13:22:46'),
(6, 5, 'test', 'High', '2026-02-20', 'test', 'completed', '2026-02-17 13:22:49'),
(7, 5, 'reryg', 'Medium', '2026-02-26', 'dfdnbchgfn', 'completed', '2026-02-18 10:50:48'),
(8, 4, 'hvkhn', 'Medium', '2026-02-25', 'bmvmb', 'todo', '2026-02-18 11:09:49'),
(17, 22, 'new login', 'High', '2026-02-25', 'Aewere', 'todo', '2026-02-19 11:17:18');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `leader_id` int(11) NOT NULL,
  `deadline` date NOT NULL,
  `total_tasks` int(11) DEFAULT 0,
  `completed_tasks` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('Active','Pending','Completed','Hold') DEFAULT 'Active',
  `progress` int(11) DEFAULT 0,
  `project_logo` varchar(255) DEFAULT NULL,
  `project_value` decimal(10,2) DEFAULT 0.00,
  `price_type` enum('Hourly','Fixed') DEFAULT 'Fixed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `leader_id`, `deadline`, `total_tasks`, `completed_tasks`, `description`, `client_name`, `created_by`, `start_date`, `priority`, `status`, `progress`, `project_logo`, `project_value`, `price_type`) VALUES
(1, 'HRMS System', 19, '2026-04-10', 20, 15, NULL, NULL, NULL, NULL, 'Medium', 'Active', 0, NULL, 0.00, 'Fixed'),
(2, 'HRMS Mobile App', 19, '0000-00-00', 0, 0, 'Test project for assigning tasks', NULL, 1, '2026-02-18', 'High', 'Active', 0, NULL, 0.00, 'Fixed'),
(3, 'HRMS Payroll Integration', 19, '2026-03-30', 0, 0, 'Integrate the new automated payroll module for all employees.', NULL, 16, '2026-02-19', 'High', 'Active', 15, NULL, 0.00, 'Fixed'),
(4, 'new project', 19, '2026-02-20', 0, 0, 'give it me in right time', NULL, 3, '2026-02-19', 'Medium', 'Active', 0, NULL, 0.00, 'Fixed');

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tasks`
--

CREATE TABLE `project_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `due_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `project_tasks`
--

INSERT INTO `project_tasks` (`id`, `project_id`, `task_title`, `description`, `assigned_to`, `priority`, `status`, `due_date`, `created_by`, `created_at`) VALUES
(3, 2, 'new app', 'xdsbgvn', 'Brian Villalobos,Stephan Peralt', 'Medium', 'Completed', '2026-02-21', 4, '2026-02-18 11:20:25'),
(4, 2, 'new app', 'xdsbgvn', 'Brian Villalobos,Stephan Peralt', 'Medium', 'In Progress', '2026-02-21', 4, '2026-02-18 11:20:56'),
(6, 3, 'Payroll section', 'complete ui', '', 'High', 'Pending', '2026-02-21', 19, '2026-02-19 09:36:01'),
(7, 3, 'Payroll UI', 'asdfdcdrd', '', 'Low', 'Pending', '2026-02-22', 19, '2026-02-19 09:40:34'),
(8, 3, 'backend ', 'backend connection ', '', 'Low', 'Pending', '2026-02-20', 19, '2026-02-19 10:19:22'),
(9, 3, 'Payroll section', 'ftf', '', 'High', 'Pending', '2026-02-21', 19, '2026-02-19 10:44:00'),
(10, 3, 'UI', 'complete ', '', 'Low', 'Pending', '2026-02-26', 19, '2026-02-19 10:57:17'),
(11, 2, 'UI', 'UI completion ', '', 'High', 'Pending', '2026-02-20', 19, '2026-02-19 11:07:19'),
(12, 3, 'backend ', 'web application', 'Grace Lee,Brian Villalobos', 'High', 'Pending', '2026-02-21', 19, '2026-02-19 11:10:58'),
(13, 1, 'UI', 'software ', 'Brian Villalobos', 'Low', 'Pending', '2026-02-27', 19, '2026-02-19 11:13:25'),
(14, 4, 'new login', 'quick', 'Brian Villalobos', 'High', 'Completed', '2026-02-20', 19, '2026-02-19 11:15:56'),
(15, 4, 'new page', 'as', 'Brian Villalobos', 'High', 'Completed', '2026-02-20', 19, '2026-02-19 11:21:35');

-- --------------------------------------------------------

--
-- Table structure for table `team_tasks`
--

CREATE TABLE `team_tasks` (
  `id` int(11) NOT NULL,
  `assigned_by_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `assigned_by_name` varchar(100) DEFAULT 'Admin',
  `assigned_by_role` varchar(50) DEFAULT 'Team Lead',
  `assigned_by_img` varchar(255) DEFAULT 'https://i.pravatar.cc/150',
  `deadline` date NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('Pending','To Do','In Progress','Completed') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_code` varchar(20) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `priority` enum('High','Medium','Low') NOT NULL,
  `department` varchar(50) NOT NULL,
  `cc_email` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('Open','In Progress','Resolved','Closed') DEFAULT 'Open',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `user_id`, `ticket_code`, `subject`, `priority`, `department`, `cc_email`, `description`, `attachment`, `status`, `created_at`) VALUES
(1, 4, 'TKT-78853', 'Laptop service ', 'High', 'Project', '', 'check the service ', 'uploads/tickets/1771412197_black.png', 'Open', '2026-02-18 10:56:38'),
(2, 4, 'TKT-96843', 'Laptop service ', 'Medium', 'IT Support', '', 'skm laptop service ', '1771412525_black.png', 'Open', '2026-02-18 11:02:06'),
(3, 22, 'TKT-42375', 'laptop service', 'High', 'IT Support', '', 'ssssssfdfdveqrvecdcdc', 'uploads/tickets/1771502832_6996fcf05cc07.png', 'Open', '2026-02-19 12:07:12'),
(4, 22, 'TKT-34701', 'laptop service', 'High', 'IT Support', '', 'sssssfwefffffff', 'uploads/tickets/1771502909_6996fd3d12fef.png', 'Open', '2026-02-19 12:08:29'),
(5, 22, 'TKT-45247', 'laptop service', 'High', 'IT Support', '', 'codmafje jdnijefdc', 'uploads/tickets/1771503474_6996ff72b3f05.png', 'Open', '2026-02-19 12:17:55'),
(6, 22, 'TKT-48241', 'laptop service', 'High', 'IT Support', '', 'nhgyugvhb ugygjojn', 'uploads/tickets/1771504161_6997022182470.png', 'Open', '2026-02-19 12:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('System Admin','HR','Manager','Team Lead','Employee','Sales','Digital Marketing','IT Admin','IT Executive','HR Executive','CFO','Accounts') NOT NULL,
  `last_password_change` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `employee_id`, `department`, `username`, `email`, `password`, `role`, `last_password_change`) VALUES
(1, 'Stephan Peralt', 'EMP-004', NULL, 'admin@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'System Admin', NULL),
(2, NULL, NULL, NULL, 'hr@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'HR', NULL),
(3, NULL, NULL, NULL, 'manager@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Manager', NULL),
(4, NULL, NULL, NULL, 'teamlead@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Team Lead', NULL),
(5, 'Stephen Peralt', 'EMP-005', NULL, 'employee@gmail.com', NULL, '$2y$10$MeAzyeP11ED/2.2mEQZqJeApbbN9.itnXZrHS5IjeddV5EGQlA45y', 'Employee', '2026-02-18 05:36:22'),
(6, NULL, NULL, NULL, 'sales@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Sales', NULL),
(7, NULL, NULL, NULL, 'dm@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Digital Marketing', NULL),
(8, NULL, NULL, NULL, 'accounts@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Accounts', NULL),
(9, NULL, NULL, NULL, 'itadmin@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'IT Admin', NULL),
(10, NULL, NULL, NULL, 'itexecutive@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'IT Executive', NULL),
(11, NULL, NULL, NULL, 'hrexecutive@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'HR Executive', NULL),
(13, NULL, NULL, NULL, 'cfo@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'CFO', NULL),
(14, 'Alice Sharma', 'EMP-HR01', NULL, 'alice.hr@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'HR', NULL),
(15, 'Bob Johnson', 'EMP-HRE01', NULL, 'bob.hrexec@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'HR Executive', NULL),
(16, 'Charlie Davis', 'EMP-MGR01', NULL, 'charlie.mgr@gmail.com', NULL, '$2y$10$aeJ0.zfTWbddzUI9GsZvr.SIUJOChtYf9JDhBDx4DVBxsY0p1At36', 'Manager', NULL),
(17, 'Diana Prince', 'EMP-CFO01', NULL, 'diana.cfo@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'CFO', NULL),
(18, 'Evan Wright', 'EMP-ACC01', NULL, 'evan.accounts@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Accounts', NULL),
(19, 'Frank Castle', 'EMP-TL01', NULL, 'frank.tl@gmail.com', NULL, '$2y$10$Xfv825pBQPW.akprf.7jq.MtpNilWehsxAKHtQ9WsRumdKrHt.wSy', 'Team Lead', NULL),
(20, 'Grace Lee', 'EMP-E01', NULL, 'grace.emp@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Employee', NULL),
(21, 'Henry Ford', 'EMP-E02', NULL, 'henry.emp@gmail.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Employee', NULL),
(22, 'Brian Villalobos', 'EMP-002', NULL, 'brian@example.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Employee', NULL),
(23, 'Julia Gomes', 'EMP-009', NULL, 'julia@example.com', NULL, '$2y$10$lz1VCMchhKgmrnXnz1x4XuEjiBTz4GOxrrHqIl9/cBu7/zymJEp/6', 'Employee', NULL),
(24, 'Aparna M A', 'EMP-007', NULL, 'aparnaemp@gmail.com', NULL, '$2y$10$Xfv825pBQPW.akprf.7jq.MtpNilWehsxAKHtQ9WsRumdKrHt.wSy', 'Employee', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wfh_requests`
--

CREATE TABLE `wfh_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `applied_date` timestamp NULL DEFAULT current_timestamp(),
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `shift` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewer_name` varchar(100) DEFAULT 'Admin Team'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `wfh_requests`
--

INSERT INTO `wfh_requests` (`id`, `user_id`, `employee_name`, `department`, `email`, `applied_date`, `start_date`, `end_date`, `shift`, `reason`, `status`, `reviewer_name`) VALUES
(1, 1, NULL, NULL, NULL, '2026-02-17 11:30:45', '2026-02-20', '2026-02-21', 'Regular', 'Internet maintenance at home', 'Approved', 'Admin Team'),
(2, 5, NULL, NULL, NULL, '2026-02-17 11:31:50', '2026-02-18', '2026-02-18', 'Regular', 'Medical reason', 'Approved', 'Admin Team'),
(3, 5, NULL, NULL, NULL, '2026-02-17 13:21:29', '2026-02-18', '2026-02-18', 'Regular', 'reee', 'Pending', 'Admin Team'),
(5, 5, NULL, NULL, NULL, '2026-02-18 07:40:13', '2026-02-19', '2026-02-19', 'Regular', 'wwwwrwt4t4t', 'Approved', 'Admin Team'),
(6, 22, 'Brian', NULL, NULL, '2026-02-19 11:03:22', '2026-02-19', '2026-02-21', 'Regular', 'off to hometown', 'Approved', 'Admin Team');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id` (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_conversation` (`conversation_id`,`created_at`);

--
-- Indexes for table `chat_participants`
--
ALTER TABLE `chat_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_conv` (`user_id`,`conversation_id`);

--
-- Indexes for table `daily_metrics`
--
ALTER TABLE `daily_metrics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_performance`
--
ALTER TABLE `employee_performance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_reporting_to` (`reporting_to`);

--
-- Indexes for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `help_articles`
--
ALTER TABLE `help_articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `help_categories`
--
ALTER TABLE `help_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hiring_requests`
--
ALTER TABLE `hiring_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payslip_requests`
--
ALTER TABLE `payslip_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `personal_targets`
--
ALTER TABLE `personal_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `personal_taskboard`
--
ALTER TABLE `personal_taskboard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leader_id` (`leader_id`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `team_tasks`
--
ALTER TABLE `team_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `unique_employee_id` (`employee_id`);

--
-- Indexes for table `wfh_requests`
--
ALTER TABLE `wfh_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_participants`
--
ALTER TABLE `chat_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `daily_metrics`
--
ALTER TABLE `daily_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_performance`
--
ALTER TABLE `employee_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `employee_skills`
--
ALTER TABLE `employee_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `help_articles`
--
ALTER TABLE `help_articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `help_categories`
--
ALTER TABLE `help_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `hiring_requests`
--
ALTER TABLE `hiring_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payslip_requests`
--
ALTER TABLE `payslip_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `personal_targets`
--
ALTER TABLE `personal_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_taskboard`
--
ALTER TABLE `personal_taskboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_members`
--
ALTER TABLE `project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_tasks`
--
ALTER TABLE `project_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `team_tasks`
--
ALTER TABLE `team_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `wfh_requests`
--
ALTER TABLE `wfh_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_performance`
--
ALTER TABLE `employee_performance`
  ADD CONSTRAINT `employee_performance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `employee_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reporting_to` FOREIGN KEY (`reporting_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `help_articles`
--
ALTER TABLE `help_articles`
  ADD CONSTRAINT `help_articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `help_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hiring_requests`
--
ALTER TABLE `hiring_requests`
  ADD CONSTRAINT `fk_hiring_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payslip_requests`
--
ALTER TABLE `payslip_requests`
  ADD CONSTRAINT `payslip_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_targets`
--
ALTER TABLE `personal_targets`
  ADD CONSTRAINT `personal_targets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_taskboard`
--
ALTER TABLE `personal_taskboard`
  ADD CONSTRAINT `personal_taskboard_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`leader_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD CONSTRAINT `fk_project_task` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_tasks`
--
ALTER TABLE `team_tasks`
  ADD CONSTRAINT `team_tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wfh_requests`
--
ALTER TABLE `wfh_requests`
  ADD CONSTRAINT `wfh_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

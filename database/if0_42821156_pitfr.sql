-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql113.infinityfree.com
-- Generation Time: Oct 01, 2026 at 10:15 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42821156_pitfr`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `target_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_id`, `target_user_id`, `action`, `details`, `old_values`, `new_values`, `created_at`, `updated_at`) VALUES
(1, 12, 13, 'user_created', 'Created a new user account.', '[]', '{\"name\":\"Nick Vincent Degamo Andales N\\/A\",\"username\":\"andalesnick@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"student\"}', '2026-09-02 14:26:32', '2026-09-02 14:26:32'),
(2, 12, 16, 'user_deactivated', 'Deactivated the user account.', '{\"is_active\":true}', '{\"is_active\":false}', '2026-09-08 17:07:43', '2026-09-08 17:07:43'),
(3, 12, 16, 'user_updated', 'Updated user account details.', '{\"name\":\"KANINBOSSING\",\"username\":\"kidmundane@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":false}', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":false}', '2026-09-08 17:08:34', '2026-09-08 17:08:34'),
(4, 12, 16, 'user_updated', 'Updated user account details.', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":false}', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":false}', '2026-09-08 17:08:35', '2026-09-08 17:08:35'),
(5, 12, 16, 'user_reactivated', 'Reactivated a deactivated user account.', '{\"is_active\":false}', '{\"is_active\":true}', '2026-09-08 17:08:42', '2026-09-08 17:08:42'),
(6, 12, 16, 'user_updated', 'Updated user account details.', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":true}', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"student\",\"requestor_type\":\"outsider\",\"is_active\":true}', '2026-09-08 17:09:31', '2026-09-08 17:09:31'),
(7, 12, 15, 'user_updated', 'Updated user account details.', '{\"name\":\"Tngina\",\"username\":\"kilgyro@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":true}', '{\"name\":\"daniel\",\"username\":\"kilgyro@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"outsider\",\"is_active\":true}', '2026-09-08 17:09:55', '2026-09-08 17:09:55'),
(8, 12, 17, 'user_created', 'Created a new user account.', '[]', '{\"name\":\"Carl Nigger Johnson\",\"username\":\"grovestreetfam@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"faculty\"}', '2026-09-24 04:55:01', '2026-09-24 04:55:01'),
(9, 12, 16, 'user_updated', 'Updated user account details.', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"student\",\"requestor_type\":\"outsider\",\"is_active\":true}', '{\"name\":\"prime\",\"username\":\"kidmundane@gmail.com\",\"role\":\"student\",\"requestor_type\":\"outsider\",\"is_active\":true}', '2026-09-27 20:56:51', '2026-09-27 20:56:51'),
(10, 12, 18, 'user_created', 'Created a new user account.', '[]', '{\"name\":\"Example Test Account\",\"username\":\"krukawa1103@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"student\"}', '2026-09-29 02:10:06', '2026-09-29 02:10:06'),
(11, 12, 18, 'user_updated', 'Updated user account details.', '{\"name\":\"Example Test Account\",\"username\":\"krukawa1103@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"student\",\"is_active\":true}', '{\"name\":\"Example Test Account\",\"username\":\"krukawa1103@gmail.com\",\"role\":\"requestor\",\"requestor_type\":\"student\",\"is_active\":true}', '2026-09-29 02:11:47', '2026-09-29 02:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `abbreviation` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`, `abbreviation`, `description`, `created_at`, `updated_at`) VALUES
(6, 'College of Technology and Engineering', 'COTE', 'Technology and engineering programs', '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(7, 'College of Teacher Education', 'CTE', 'Education and teacher training programs', '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(8, 'College of Maritime Education', 'COMED', 'Maritime and Nautical programs', '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(9, 'College of Arts and Sciences', 'CAS', 'Arts and Sciences programs', '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(10, 'College of Graduate Studies', 'CGS', 'Doctoral and Masteral programs', '2026-09-01 11:03:39', '2026-09-01 11:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `college_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `college_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(21, 6, 'Information Technology', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(22, 6, 'Electrical Engineering', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(23, 6, 'Mechanical Engineering', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(24, 6, 'Industrial Engineering', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(25, 6, 'Industrial Technology', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(26, 7, 'Elementary Education', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(27, 7, 'Secondary Education', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(28, 7, 'Social Science Education', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(29, 7, 'Technical-Vocational Teacher Education', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(30, 8, 'Marine Engineering', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(31, 8, 'Marine Transportation', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(32, 9, 'Language and Literature', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(33, 9, 'Mathematics and Science', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(34, 9, 'Social Sciences', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(35, 9, 'Business Administration', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(36, 9, 'Communication', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(37, 9, 'Marine Biology', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(38, 9, 'Hospitality Management', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(39, 10, 'Doctoral Programs', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39'),
(40, 10, 'Masteral Programs', NULL, '2026-09-01 11:03:39', '2026-09-01 11:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `quantity_available` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `custodian_id` bigint(20) UNSIGNED NOT NULL,
  `authorized_custodian_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `quantity`, `quantity_available`, `is_active`, `custodian_id`, `authorized_custodian_ids`, `created_at`, `updated_at`) VALUES
(1, 'Sound System', 1, 1, 1, 8, '[]', '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(2, 'Wireless Microphones', 1, 1, 1, 8, '[]', '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(3, 'Non-Wireless Microphones', 1, 1, 1, 8, '[]', '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(4, 'Canopies', 10, 10, 1, 9, '[]', '2026-09-01 11:03:44', '2026-09-22 05:52:14'),
(5, 'Industrial Fans', 6, 6, 1, 10, '[11]', '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(6, 'Iwata Cooler Fans', 4, 4, 1, 10, '[11]', '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(7, 'Tables', 10, 10, 1, 9, '[]', '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(8, 'Monobloc Chairs', 600, 600, 1, 9, '[]', '2026-09-01 11:03:44', '2026-09-01 11:03:44');

-- --------------------------------------------------------

--
-- Table structure for table `facility_requests`
--

CREATE TABLE `facility_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `control_number` varchar(30) NOT NULL,
  `date_requested` date NOT NULL,
  `department` varchar(100) NOT NULL,
  `organization_name` varchar(191) DEFAULT NULL,
  `request_context` varchar(50) DEFAULT NULL,
  `student_organization_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name_of_activity` varchar(200) NOT NULL,
  `purpose` text DEFAULT NULL,
  `expected_participants` int(10) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `reservation_duration` enum('specific_time','whole_day','whole-day','whole day') NOT NULL DEFAULT 'specific_time',
  `venue` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `equipment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `equipment_quantities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `other_venue` varchar(200) DEFAULT NULL,
  `requested_by_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected','needs_revision') NOT NULL DEFAULT 'pending',
  `venue_status` enum('pending','approved','rejected','needs_revision') NOT NULL DEFAULT 'pending',
  `equipment_status` enum('pending','approved','rejected','needs_revision') NOT NULL DEFAULT 'pending',
  `equipment_custodian_statuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_by_id` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `venue_notes` text DEFAULT NULL,
  `equipment_notes` text DEFAULT NULL,
  `proposal_file` varchar(255) DEFAULT NULL,
  `activity_proposal_file` varchar(255) DEFAULT NULL,
  `igp_receipt_file` varchar(255) DEFAULT NULL,
  `e_signature_file` varchar(255) DEFAULT NULL,
  `venue_approval_signature_file` varchar(255) DEFAULT NULL,
  `equipment_approval_signature_file` varchar(255) DEFAULT NULL,
  `final_approval_signature_file` varchar(255) DEFAULT NULL,
  `final_approval_signature` varchar(255) DEFAULT NULL,
  `document_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `equipment_returned_status` enum('pending','partial','returned','fulfilled','not_applicable') NOT NULL DEFAULT 'pending',
  `equipment_returned_by` varchar(100) DEFAULT NULL,
  `equipment_returned_date` datetime DEFAULT NULL,
  `equipment_return_notes` text DEFAULT NULL,
  `equipment_return_damaged_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `equipment_return_missing_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `equipment_return_damage_remarks` text DEFAULT NULL,
  `equipment_return_missing_remarks` text DEFAULT NULL,
  `equipment_returned_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `priority` enum('regular','institutional') NOT NULL DEFAULT 'regular',
  `requested_priority` enum('regular','institutional') DEFAULT NULL,
  `requested_is_emergency` tinyint(1) NOT NULL DEFAULT 0,
  `emergency_justification` text DEFAULT NULL,
  `is_emergency` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `venue_approval_signature` varchar(255) DEFAULT NULL,
  `equipment_approval_signature` varchar(255) DEFAULT NULL,
  `approval_signature_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

--
-- Dumping data for table `facility_requests`
--

INSERT INTO `facility_requests` (`id`, `control_number`, `date_requested`, `department`, `organization_name`, `request_context`, `student_organization_id`, `name_of_activity`, `purpose`, `expected_participants`, `start_date`, `end_date`, `start_time`, `end_time`, `reservation_duration`, `venue`, `equipment`, `equipment_quantities`, `other_venue`, `requested_by_id`, `status`, `venue_status`, `equipment_status`, `equipment_custodian_statuses`, `approved_by`, `approved_by_id`, `approved_date`, `notes`, `venue_notes`, `equipment_notes`, `proposal_file`, `activity_proposal_file`, `igp_receipt_file`, `e_signature_file`, `venue_approval_signature_file`, `equipment_approval_signature_file`, `final_approval_signature_file`, `final_approval_signature`, `document_metadata`, `equipment_returned_status`, `equipment_returned_by`, `equipment_returned_date`, `equipment_return_notes`, `equipment_return_damaged_quantity`, `equipment_return_missing_quantity`, `equipment_return_damage_remarks`, `equipment_return_missing_remarks`, `equipment_returned_items`, `priority`, `requested_priority`, `requested_is_emergency`, `emergency_justification`, `is_emergency`, `created_at`, `updated_at`, `venue_approval_signature`, `equipment_approval_signature`, `approval_signature_meta`, `deleted_at`) VALUES
(1, 'FER-2026-9000', '2026-09-04', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'asdsadasda', 'asdasdasda', 46, '2026-09-07', '2026-09-11', '08:00:00', '23:59:00', 'specific_time', '[\"Balay Alumni\"]', '[\"Sound System\"]', '{\"Sound System\":1}', NULL, 13, 'cancelled', 'cancelled', 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-9000_activity_proposal_20260904_120153_e17ba553.pdf', NULL, 'FER-2026-9000_e_signature_20260904_120153_3d3f561d.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-04 12:01:53\",\"original_name\":\"Gold And Brown Modern Certificate.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-04 12:01:53\",\"original_name\":\"ENTRANCE.png\",\"source\":\"request_upload\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-04 19:01:53', '2026-09-04 22:54:45', NULL, NULL, NULL, NULL),
(2, 'FER-2026-695', '2026-09-04', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'Mad Dogg\'s Party Homie', 'asdasdasdasda', 100, '2026-09-15', '2026-09-15', '08:00:00', '23:59:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\"]', '{\"Sound System\":1}', NULL, 13, 'pending', 'pending', 'pending', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-695_activity_proposal_20260904_124403_3b428627.pdf', NULL, 'FER-2026-695_e_signature_20260904_124403_c7c41626.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-04 12:44:03\",\"original_name\":\"Gold And Brown Modern Certificate.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-04 12:44:03\",\"original_name\":\"ENTRANCE.png\",\"source\":\"request_upload\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-04 19:44:03', '2026-09-08 16:46:06', '145b72805d0cc4081c779ff260c1621d461a7714b2fa1cccf8a113b586e95ffd', '777d012ba59a45423c586de0e14e90a551b1f37d8a2717adf14f8b6e0937fc52', '{\"venue\":\"{\\\"request_id\\\":2,\\\"approver_id\\\":6,\\\"type\\\":\\\"venue\\\",\\\"time\\\":\\\"2026-09-04T09:29:27.070754Z\\\"}\",\"equipment\":[\"{\\\"request_id\\\":2,\\\"approver_id\\\":8,\\\"type\\\":\\\"equipment\\\",\\\"time\\\":\\\"2026-09-04T09:44:04.691831Z\\\"}\"]}', NULL),
(3, 'FER-2026-9911', '2026-09-08', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'grand', 'party', 101, '2026-09-08', '2026-09-08', '08:00:00', '12:00:00', 'specific_time', '[\"Oval Grounds\"]', '[\"Sound System\",\"Wireless Microphones\",\"Industrial Fans\",\"Iwata Cooler Fans\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Industrial Fans\":3,\"Iwata Cooler Fans\":2}', NULL, 13, 'pending', 'pending', 'pending', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-9911_activity_proposal_20260908_095031_2e1242c9.jpg', NULL, 'FER-2026-9911_e_signature_20260908_095031_d8c5aea0.jpg', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-08 09:50:31\",\"original_name\":\"download (1).jpg\"},\"e_signature\":{\"uploaded_at\":\"2026-09-08 09:50:31\",\"original_name\":\"download.jpg\",\"source\":\"request_upload\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-08 16:50:31', '2026-09-08 16:51:15', NULL, NULL, NULL, NULL),
(4, 'FER-2026-835', '2026-09-08', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'nsak', 'sfasfsa', 100, '2026-09-08', '2026-09-08', '08:00:00', '12:00:00', 'specific_time', '[\"Oval Grounds\"]', '[\"Sound System\"]', '{\"Sound System\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-835_activity_proposal_20260908_105412_f78ebb7b.png', NULL, 'FER-2026-835_e_signature_20260908_105412_5b71684d.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-08 10:54:12\",\"original_name\":\"Screenshot (1).png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-08 10:54:12\",\"original_name\":\"Screenshot 2026-09-08 102510.png\",\"source\":\"request_upload\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-08 17:54:12', '2026-09-08 17:54:12', NULL, NULL, NULL, NULL),
(5, 'FER-2026-6168', '2026-09-14', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'asdsad', 'asdasdasd', 310, '2026-09-14', '2026-09-14', '08:00:00', '23:59:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\",\"Monobloc Chairs\",\"Aircon\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1,\"Monobloc Chairs\":1,\"Aircon\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, 'Capacity warning: The combined capacity of your selected venues is 300, which is insufficient for 310 participants. The request will still be submitted for review.', NULL, NULL, NULL, 'FER-2026-6168_activity_proposal_20260914_153456_f1660781.jpg', NULL, 'FER-2026-6168_request_signature_20260914_153456_f833acbb.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-14 15:34:56\",\"original_name\":\"kayde.jpg\"},\"e_signature\":{\"uploaded_at\":\"2026-09-14 15:34:56\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL, NULL, NULL, NULL),
(6, 'FER-2026-8301', '2026-09-15', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'MONEY LAUNDERING', 'pautangon kwarta si john rey pamplona nya bun ogon', 10, '2026-09-15', '2026-09-15', '08:00:00', '23:59:00', 'specific_time', '[\"Covered Court\"]', '[\"Sound System\",\"Wireless Microphones\"]', '{\"Sound System\":1,\"Wireless Microphones\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-8301_activity_proposal_20260915_090243_aba26da8.png', NULL, 'FER-2026-8301_request_signature_20260915_090243_7800c70c.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-15 09:02:43\",\"original_name\":\"1.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-15 09:02:43\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-15 16:02:43', '2026-09-15 16:02:43', NULL, NULL, NULL, NULL),
(7, 'FER-2026-9792', '2026-09-15', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'asdasd', 'asdasd', 600, '2026-09-15', '2026-09-15', '08:00:00', '23:59:00', 'specific_time', '[\"Covered Court\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1,\"Monobloc Chairs\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, 'Capacity warning: The combined capacity of your selected venues is 200, which is insufficient for 600 participants. The request will still be submitted for review.', NULL, NULL, NULL, 'FER-2026-9792_activity_proposal_20260915_100230_d00cb2e2.png', NULL, 'FER-2026-9792_request_signature_20260915_100230_35fa6fa2.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-15 10:02:30\",\"original_name\":\"Screenshot 2026-09-15 095928.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-15 10:02:30\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL, NULL, NULL, NULL),
(8, 'FER-2026-9574', '2026-09-17', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'aasdas', 'asdasdas', 60, '2026-09-18', '2026-09-18', '08:00:00', '23:59:00', 'specific_time', '[\"Balay Alumni\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\",\"Aircon\",\"Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1,\"Aircon\":1,\"Chairs\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, 'Capacity warning: The combined capacity of your selected venues is 50, which is insufficient for 60 participants. The request will still be submitted for review.', NULL, NULL, NULL, 'FER-2026-9574_activity_proposal_20260917_192708_8677b6eb.pdf', NULL, 'FER-2026-9574_request_signature_20260917_192708_5ee5d2f1.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-17 19:27:08\",\"original_name\":\"1.-INTRODUCTION-and-SCOPE-2026.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-17 19:27:08\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL, NULL, NULL, NULL),
(9, 'FER-2026-6405', '2026-09-17', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'asdasdasd', 'asdasdasd', 60, '2026-09-19', '2026-09-19', '08:00:00', '12:00:00', 'specific_time', '[\"Covered Court\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1}', NULL, 13, 'rejected', 'pending', 'rejected', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, 'FER-2026-6405_activity_proposal_20260917_205427_9311e034.png', NULL, 'FER-2026-6405_request_signature_20260917_205427_54e8b564.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-17 20:54:27\",\"original_name\":\"gsf.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-17 20:54:27\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-18 03:54:27', '2026-09-22 04:05:22', NULL, NULL, NULL, NULL),
(10, 'FER-2026-4143', '2026-09-23', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'asdasdas', 'asdasdasdsad', 45, '2026-09-23', '2026-09-23', '08:00:00', '23:59:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-4143_activity_proposal_20260923_111154_e51b2a43.pdf', NULL, 'FER-2026-4143_request_signature_20260923_111154_37bd3ab8.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-23 11:11:54\",\"original_name\":\"Maze_building-up-study.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-23 11:11:54\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-23 18:11:54', '2026-09-23 18:11:54', NULL, NULL, NULL, NULL),
(11, 'FER-2026-5435', '2026-09-23', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'asdad1231ewas', 'dsadcascs', 100, '2026-09-23', '2026-09-23', '08:00:00', '23:59:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1}', NULL, 13, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-5435_activity_proposal_20260923_212917_893e5efd.pdf', NULL, 'FER-2026-5435_request_signature_20260923_212917_4cf88858.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-23 21:29:17\",\"original_name\":\"Maze_building-up-study.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-23 21:29:17\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL, NULL, NULL, NULL),
(12, 'FER-2026-6994', '2026-09-23', 'Information Technology', 'Bonded Information Technology Students (BITS)', 'personal', 1, 'vvvvvvvvvv', 'vvvvvvvvv', 100, '2026-09-23', '2026-09-23', '08:00:00', '23:59:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1}', NULL, 13, 'rejected', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-6994_activity_proposal_20260923_213233_7f59bc37.pdf', NULL, 'FER-2026-6994_request_signature_20260923_213233_0297987d.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-23 21:32:33\",\"original_name\":\"Maze_building-up-study.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-23 21:32:33\",\"original_name\":\"13_20260912204631.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-24 04:32:33', '2026-09-27 20:57:27', NULL, NULL, NULL, NULL),
(13, 'FER-2026-6954', '2026-09-23', 'CEO COMPANY', 'CEO COMPANY', 'outside_organization', NULL, 'NORP', 'qeqwewewt', 20, '2026-09-24', '2026-09-24', '08:00:00', '23:59:00', 'specific_time', '[\"Balay Alumni\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\",\"Aircon\",\"Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1,\"Aircon\":1,\"Chairs\":1}', NULL, 14, 'rejected', 'rejected', 'pending', NULL, NULL, NULL, NULL, 'Emergency justification: kalibangon\nUrgent request submitted for administrative review; venue conflicts will be handled during approval.', '', NULL, NULL, NULL, 'FER-2026-6954_igp_receipt_20260923_222407_2f32b496.pdf', 'FER-2026-6954_request_signature_20260923_222407_6140fd8c.png', NULL, NULL, NULL, NULL, '{\"igp_receipt\":{\"uploaded_at\":\"2026-09-23 22:24:07\",\"original_name\":\"Maze_building-up-study.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-23 22:24:07\",\"original_name\":\"14_20260919233559.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 1, 'kalibangon', 1, '2026-09-24 05:24:07', '2026-09-25 02:35:21', NULL, NULL, NULL, NULL),
(14, 'FER-2026-8849', '2026-09-28', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'Prime', 'Testing', 40, '2026-09-30', '2026-09-30', '08:00:00', '12:00:00', 'specific_time', '[\"Balay Alumni\"]', '[\"Sound System\",\"Wireless Microphones\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Monobloc Chairs\":130}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-8849_activity_proposal_20260928_223919_bd5d4bcd.jpg', NULL, 'FER-2026-8849_request_signature_20260928_223919_a37818b7.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-28 22:39:19\",\"original_name\":\"17906063473735148392945257561511.jpg\"},\"e_signature\":{\"uploaded_at\":\"2026-09-28 22:39:19\",\"original_name\":\"18_20260928223504.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-29 05:39:19', '2026-09-29 05:39:19', NULL, NULL, NULL, NULL),
(15, 'FER-2026-9278', '2026-09-28', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'asdas', 'asdasd', 100, '2026-09-29', '2026-09-29', '08:00:00', '23:59:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\"]', '{\"Sound System\":1}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-9278_activity_proposal_20260928_224436_7db19d90.pdf', NULL, 'FER-2026-9278_request_signature_20260928_224436_09d1d0d9.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-28 22:44:36\",\"original_name\":\"Maze_building-up-study.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-28 22:44:36\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-29 05:44:36', '2026-09-29 05:44:36', NULL, NULL, NULL, NULL),
(16, 'FER-2026-5318', '2026-09-28', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'Open Forum', 'Freely ask questions', 150, '2026-09-30', '2026-09-30', '13:00:00', '16:00:00', 'specific_time', '[\"Covered Court\"]', '[\"Sound System\",\"Wireless Microphones\",\"Iwata Cooler Fans\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Iwata Cooler Fans\":4,\"Monobloc Chairs\":160}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-5318_activity_proposal_20260928_230702_93f727be.png', NULL, 'FER-2026-5318_request_signature_20260928_230702_f315f734.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-28 23:07:02\",\"original_name\":\"four_lenses_diagram_landscape.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-28 23:07:02\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL, NULL, NULL, NULL);
INSERT INTO `facility_requests` (`id`, `control_number`, `date_requested`, `department`, `organization_name`, `request_context`, `student_organization_id`, `name_of_activity`, `purpose`, `expected_participants`, `start_date`, `end_date`, `start_time`, `end_time`, `reservation_duration`, `venue`, `equipment`, `equipment_quantities`, `other_venue`, `requested_by_id`, `status`, `venue_status`, `equipment_status`, `equipment_custodian_statuses`, `approved_by`, `approved_by_id`, `approved_date`, `notes`, `venue_notes`, `equipment_notes`, `proposal_file`, `activity_proposal_file`, `igp_receipt_file`, `e_signature_file`, `venue_approval_signature_file`, `equipment_approval_signature_file`, `final_approval_signature_file`, `final_approval_signature`, `document_metadata`, `equipment_returned_status`, `equipment_returned_by`, `equipment_returned_date`, `equipment_return_notes`, `equipment_return_damaged_quantity`, `equipment_return_missing_quantity`, `equipment_return_damage_remarks`, `equipment_return_missing_remarks`, `equipment_returned_items`, `priority`, `requested_priority`, `requested_is_emergency`, `emergency_justification`, `is_emergency`, `created_at`, `updated_at`, `venue_approval_signature`, `equipment_approval_signature`, `approval_signature_meta`, `deleted_at`) VALUES
(17, 'FER-2026-1685', '2026-09-29', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'Experimental', 'Remote Testing', 20, '2026-09-29', '2026-09-30', '08:00:00', '23:59:00', 'specific_time', '[\"Gymnasium\"]', '[\"Tables\"]', '{\"Tables\":1}', NULL, 18, 'cancelled', 'cancelled', 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-1685_activity_proposal_20260929_062409_d78998c2.png', NULL, 'FER-2026-1685_request_signature_20260929_062409_6acb2444.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-29 06:24:09\",\"original_name\":\"motorcycle.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-29 06:24:09\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-29 13:24:09', '2026-09-29 13:35:25', NULL, NULL, NULL, NULL),
(18, 'FER-2026-6633', '2026-09-29', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'OJT Orientation', 'To let the OJT students know and understand what are the do\'s and don\'ts about the internship.', 100, '2026-09-30', '2026-09-30', '08:00:00', '12:00:00', 'specific_time', '[\"Covered Court\"]', '[\"Sound System\",\"Wireless Microphones\",\"Industrial Fans\",\"Iwata Cooler Fans\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Industrial Fans\":4,\"Iwata Cooler Fans\":2,\"Monobloc Chairs\":100}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, 'Emergency justification: The OJT students will soon to be deploy\nUrgent request submitted for administrative review; venue conflicts will be handled during approval.', NULL, NULL, NULL, 'FER-2026-6633_activity_proposal_20260929_083643_dd290960.jpg', NULL, 'FER-2026-6633_request_signature_20260929_083643_e6636c08.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-29 08:36:43\",\"original_name\":\"Screenshot_2026-09-28-19-53-02-30_be80aec1db9a2b53c9d399db0c602181.jpg\"},\"e_signature\":{\"uploaded_at\":\"2026-09-29 08:36:43\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 1, 'The OJT students will soon to be deploy', 1, '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL, NULL, NULL, NULL),
(19, 'FER-2026-033', '2026-09-29', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'SDSD', 'DSSDS', 50, '2026-09-30', '2026-09-30', '08:00:00', '23:59:00', 'specific_time', '[\"Volleyball Court\"]', '[\"Sound System\",\"Wireless Microphones\"]', '{\"Sound System\":1,\"Wireless Microphones\":1}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, 'Emergency justification: KOKO\nUrgent request submitted for administrative review; venue conflicts will be handled during approval.', NULL, NULL, NULL, 'FER-2026-033_activity_proposal_20260929_094807_d05bd8f6.png', NULL, 'FER-2026-033_request_signature_20260929_094807_b405ae7d.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-29 09:48:07\",\"original_name\":\"Blue Sea Turtle Sticker-Photoroom.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-29 09:48:07\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 1, 'KOKO', 1, '2026-09-29 16:48:07', '2026-09-29 16:48:07', NULL, NULL, NULL, NULL),
(20, 'FER-2026-3105', '2026-09-29', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'assembly', 'to gather members', 50, '2026-09-29', '2026-09-29', '13:00:00', '17:00:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Wireless Microphones\",\"Tables\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Tables\":4,\"Monobloc Chairs\":75}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, 'Emergency justification: unya nang hapon\nUrgent request submitted for administrative review; venue conflicts will be handled during approval.', NULL, NULL, NULL, 'FER-2026-3105_activity_proposal_20260929_103029_df560bf2.pdf', NULL, 'FER-2026-3105_request_signature_20260929_103029_11cace52.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-29 10:30:29\",\"original_name\":\"PITM 160-2026 54TH CHARTER ANNIVERSARY PROGRAM AND INVESTITURE AND INSTALLATION OF THE 8TH PRESIDENT.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-29 10:30:29\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 1, 'unya nang hapon', 1, '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL, NULL, NULL, NULL),
(21, 'FER-2026-1653', '2026-09-29', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'election', 'to elect', 50, '2026-09-29', '2026-09-29', '13:00:00', '17:00:00', 'specific_time', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Wireless Microphones\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Monobloc Chairs\":80}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-1653_activity_proposal_20260929_103727_4653a150.pdf', NULL, 'FER-2026-1653_request_signature_20260929_103727_415dedd8.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-29 10:37:27\",\"original_name\":\"PITM 160-2026 54TH CHARTER ANNIVERSARY PROGRAM AND INVESTITURE AND INSTALLATION OF THE 8TH PRESIDENT.pdf\"},\"e_signature\":{\"uploaded_at\":\"2026-09-29 10:37:27\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-29 17:37:27', '2026-09-29 17:37:27', NULL, NULL, NULL, NULL),
(22, 'FER-2026-2917', '2026-09-30', 'Elementary Education', 'Molders of Young Minds (MOYM)', 'personal', 17, 'event', 'school purposes', 100, '2026-09-30', '2026-10-01', '08:00:00', '23:59:00', 'specific_time', '[\"Gymnasium\"]', '[\"Sound System\",\"Wireless Microphones\",\"Non-Wireless Microphones\",\"Tables\",\"Monobloc Chairs\"]', '{\"Sound System\":1,\"Wireless Microphones\":1,\"Non-Wireless Microphones\":1,\"Tables\":1,\"Monobloc Chairs\":1}', NULL, 18, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FER-2026-2917_activity_proposal_20260930_080303_d2e1ed59.png', NULL, 'FER-2026-2917_request_signature_20260930_080303_f1fe8103.png', NULL, NULL, NULL, NULL, '{\"activity_proposal\":{\"uploaded_at\":\"2026-09-30 08:03:03\",\"original_name\":\"IMG_6954.png\"},\"e_signature\":{\"uploaded_at\":\"2026-09-30 08:03:03\",\"original_name\":\"18_20260928224038.png\",\"source\":\"saved_account_signature\"}}', 'pending', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, 'regular', NULL, 0, NULL, 0, '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'public',
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`) VALUES
(1, 'default', '{\"uuid\":\"2d497904-cfe7-436c-8862-2950c3e4e4ab\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:1;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-9000\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:1:{i:0;i:8;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1788494513,\"delay\":null}', 0, NULL, 1788494513, 1788494513),
(2, 'default', '{\"uuid\":\"4eaca82d-86da-40f4-9761-f7b9b04cf344\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:2;s:13:\\\"controlNumber\\\";s:12:\\\"FER-2026-695\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:2:{i:0;i:8;i:1;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1788497043,\"delay\":null}', 0, NULL, 1788497043, 1788497043),
(3, 'default', '{\"uuid\":\"1ca90555-9f49-4625-8518-e01d844ab47b\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:13;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":9:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:5:{i:0;s:13:\\\"requestVenues\\\";i:1;s:19:\\\"requestVenues.venue\\\";i:2;s:19:\\\"reservationSchedule\\\";i:3;s:16:\\\"requestEquipment\\\";i:4;s:26:\\\"requestEquipment.equipment\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:14:\\\"venue_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:5:\\\"actor\\\";s:14:\\\"ARLENE L. SALA\\\";s:14:\\\"venueCustodian\\\";N;s:19:\\\"equipmentCustodians\\\";a:0:{}s:12:\\\"supplyOffice\\\";N;s:14:\\\"conflictReason\\\";N;s:2:\\\"id\\\";s:36:\\\"d9c40701-61cc-470b-9c14-cd8cd3324542\\\";}s:4:\\\"data\\\";a:9:{s:10:\\\"request_id\\\";i:2;s:14:\\\"control_number\\\";s:12:\\\"FER-2026-695\\\";s:8:\\\"activity\\\";s:22:\\\"Mad Dogg\'s Party Homie\\\";s:6:\\\"status\\\";s:14:\\\"venue_approved\\\";s:7:\\\"message\\\";s:80:\\\"Your request status has been updated to Venue_approved. Control No: FER-2026-695\\\";s:5:\\\"route\\\";s:33:\\\"https:\\/\\/pitfr-rms.rf.gd\\/request\\/2\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:6:\\\"actors\\\";a:3:{s:15:\\\"venue_custodian\\\";N;s:20:\\\"equipment_custodians\\\";a:0:{}s:13:\\\"supply_office\\\";N;}s:10:\\\"updated_at\\\";s:19:\\\"2026-09-04 17:29:29\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1788514169,\"delay\":null}', 0, NULL, 1788514169, 1788514169),
(4, 'default', '{\"uuid\":\"b730c58c-27ad-4e76-83b6-4702ab62a0bc\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:13;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":9:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:3:{i:0;s:9:\\\"requester\\\";i:1;s:13:\\\"requestVenues\\\";i:2;s:19:\\\"requestVenues.venue\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:14:\\\"needs_revision\\\";s:5:\\\"notes\\\";s:37:\\\"Needs revision before final approval.\\\";s:5:\\\"actor\\\";s:13:\\\"Administrator\\\";s:14:\\\"venueCustodian\\\";N;s:19:\\\"equipmentCustodians\\\";a:0:{}s:12:\\\"supplyOffice\\\";N;s:14:\\\"conflictReason\\\";N;s:2:\\\"id\\\";s:36:\\\"1856d41d-95db-4b3b-9892-fe2a1f498a94\\\";}s:4:\\\"data\\\";a:9:{s:10:\\\"request_id\\\";i:2;s:14:\\\"control_number\\\";s:12:\\\"FER-2026-695\\\";s:8:\\\"activity\\\";s:22:\\\"Mad Dogg\'s Party Homie\\\";s:6:\\\"status\\\";s:14:\\\"needs_revision\\\";s:7:\\\"message\\\";s:80:\\\"Your request status has been updated to Needs_revision. Control No: FER-2026-695\\\";s:5:\\\"route\\\";s:33:\\\"https:\\/\\/pitfr-rms.rf.gd\\/request\\/2\\\";s:5:\\\"notes\\\";s:37:\\\"Needs revision before final approval.\\\";s:6:\\\"actors\\\";a:3:{s:15:\\\"venue_custodian\\\";N;s:20:\\\"equipment_custodians\\\";a:0:{}s:13:\\\"supply_office\\\";N;}s:10:\\\"updated_at\\\";s:19:\\\"2026-09-04 20:18:22\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1788524302,\"delay\":null}', 0, NULL, 1788524302, 1788524302),
(5, 'default', '{\"uuid\":\"322bdad2-63ce-477a-a667-3c009200973d\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:3;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-9911\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:4:{i:0;i:8;i:1;i:11;i:2;i:10;i:3;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1788832232,\"delay\":null}', 0, NULL, 1788832232, 1788832232),
(6, 'default', '{\"uuid\":\"eb3b7dea-7210-4e72-9070-486d0b955a21\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:4;s:13:\\\"controlNumber\\\";s:12:\\\"FER-2026-835\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:2:{i:0;i:8;i:1;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1788836052,\"delay\":null}', 0, NULL, 1788836052, 1788836052),
(7, 'default', '{\"uuid\":\"8793a30e-59ef-41ee-aed7-a307b0416d95\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:5;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-6168\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:1:{i:0;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789371296,\"delay\":null}', 0, NULL, 1789371296, 1789371296),
(8, 'default', '{\"uuid\":\"651e2849-10b5-4fa5-8e80-6a29cc8e9461\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:6;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-8301\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:2:{i:0;i:8;i:1;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789434163,\"delay\":null}', 0, NULL, 1789434163, 1789434163),
(9, 'default', '{\"uuid\":\"8d20ac1d-d053-4c7c-b556-3763eb08a2d5\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:7;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-9792\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789437750,\"delay\":null}', 0, NULL, 1789437750, 1789437750),
(10, 'default', '{\"uuid\":\"b63d03c2-bc88-45f1-9c67-057707426b30\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:8;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-9574\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:1:{i:0;i:5;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789644428,\"delay\":null}', 0, NULL, 1789644428, 1789644428),
(11, 'default', '{\"uuid\":\"36203d42-f0fa-4537-842a-3306f6046297\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:9;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-6405\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1789649667,\"delay\":null}', 0, NULL, 1789649667, 1789649667),
(12, 'default', '{\"uuid\":\"8edf707a-2b0f-43fd-8173-4491f5b54d27\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:10;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-4143\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790133115,\"delay\":null}', 0, NULL, 1790133115, 1790133115),
(13, 'default', '{\"uuid\":\"4f2a7e7e-cb7d-44e6-9933-4a4442bc70c8\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:11;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-5435\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790170158,\"delay\":null}', 0, NULL, 1790170158, 1790170158);
INSERT INTO `jobs` (`id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`) VALUES
(14, 'default', '{\"uuid\":\"6ceedb10-14c5-4121-ad29-f364dd1c790b\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:12;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-6994\\\";s:8:\\\"userName\\\";s:31:\\\"Nick Vincent Degamo Andales N\\/A\\\";s:7:\\\"ownerId\\\";i:13;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790170353,\"delay\":null}', 0, NULL, 1790170353, 1790170353),
(15, 'default', '{\"uuid\":\"12cd7dbd-bd2d-4e7c-b265-a41324b65444\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:13;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-6954\\\";s:8:\\\"userName\\\";s:3:\\\"CEO\\\";s:7:\\\"ownerId\\\";i:14;s:12:\\\"custodianIds\\\";a:1:{i:0;i:5;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790173448,\"delay\":null}', 0, NULL, 1790173448, 1790173448),
(16, 'default', '{\"uuid\":\"969a82cb-9b03-41a9-854d-9cdaddd50d67\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:14;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-8849\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:5;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790606360,\"delay\":null}', 0, NULL, 1790606360, 1790606360),
(17, 'default', '{\"uuid\":\"0f8a9fee-e4bf-45b7-8bba-c1c6792b9e08\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:15;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-9278\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:2:{i:0;i:8;i:1;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790606677,\"delay\":null}', 0, NULL, 1790606677, 1790606677),
(18, 'default', '{\"uuid\":\"c1e8e9a2-ac4e-415d-89f0-d7fd99327cec\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:16;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-5318\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:5:{i:0;i:8;i:1;i:11;i:2;i:10;i:3;i:9;i:4;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790608022,\"delay\":null}', 0, NULL, 1790608022, 1790608022),
(19, 'default', '{\"uuid\":\"0b42da32-6921-447a-b35f-10a61c8e61ff\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:17;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1685\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:2:{i:0;i:9;i:1;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790634249,\"delay\":null}', 0, NULL, 1790634249, 1790634249),
(20, 'default', '{\"uuid\":\"6129ac68-840f-409c-bb5f-d5fba693d4af\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:18;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-6633\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:5:{i:0;i:8;i:1;i:11;i:2;i:10;i:3;i:9;i:4;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790642203,\"delay\":null}', 0, NULL, 1790642203, 1790642203),
(21, 'default', '{\"uuid\":\"3bbc419c-6fb8-4740-b646-b02599c95565\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:19;s:13:\\\"controlNumber\\\";s:12:\\\"FER-2026-033\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:2:{i:0;i:8;i:1;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790646487,\"delay\":null}', 0, NULL, 1790646487, 1790646487),
(22, 'default', '{\"uuid\":\"82c9550a-5c9b-4152-952f-fc6583e8523d\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:20;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-3105\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790649029,\"delay\":null}', 0, NULL, 1790649029, 1790649029),
(23, 'default', '{\"uuid\":\"5555aa28-7cac-4730-a136-4505e0148859\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:21;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1653\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:6;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790649447,\"delay\":null}', 0, NULL, 1790649447, 1790649447),
(24, 'default', '{\"uuid\":\"ca11bd50-fd22-4110-9e02-e73132d7d226\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":5:{s:9:\\\"requestId\\\";i:22;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-2917\\\";s:8:\\\"userName\\\";s:20:\\\"Example Test Account\\\";s:7:\\\"ownerId\\\";i:18;s:12:\\\"custodianIds\\\";a:3:{i:0;i:8;i:1;i:9;i:2;i:7;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1790726583,\"delay\":null}', 0, NULL, 1790726583, 1790726583);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedules`
--

CREATE TABLE `maintenance_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `venue_id` bigint(20) UNSIGNED DEFAULT NULL,
  `equipment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_03_21_104247_create_users_table', 1),
(2, '2026_03_21_104331_create_venues_table', 1),
(3, '2026_03_21_104429_create_equipment_table', 1),
(4, '2026_03_21_104454_create_facility_requests_table', 1),
(5, '2026_03_21_132204_add_department_to_users_table', 1),
(6, '2026_03_21_132545_add_quantity_to_equipment_table', 1),
(7, '2026_03_22_060557_add_equipment_quantities_to_facility_requests_table', 1),
(8, '2026_03_22_063212_create_notifications_table', 1),
(9, '2026_04_02_095912_add_return_tracking_to_facility_requests_table', 1),
(10, '2026_04_02_103936_add_equipment_returned_items_to_facility_requests_table', 1),
(11, '2026_04_02_105700_create_jobs_table', 1),
(12, '2026_04_02_112935_add_priority_to_facility_requests_table', 1),
(13, '2026_04_02_124733_create_request_histories_table', 1),
(14, '2026_04_02_133435_add_requesting_end_date_to_facility_requests_table', 1),
(15, '2026_04_02_140404_add_equipment_custodian_statuses_to_facility_requests_table', 1),
(16, '2026_04_03_034857_add_end_time_to_facility_requests_table', 1),
(17, '2026_05_01_001553_add_capacity_to_venues', 1),
(18, '2026_05_02_091351_create_personal_access_tokens_table', 1),
(19, '2026_05_02_120000_add_supply_office_role_to_users_enum', 1),
(20, '2026_05_03_152011_add_proposal_file_to_facility_requests_table', 1),
(21, '2026_06_20_000001_add_approval_signatures_to_facility_requests', 1),
(22, '2026_06_20_000001_consolidate_user_roles_add_requestor_details', 1),
(23, '2026_07_20_000002_ensure_requestor_details_columns', 1),
(24, '2026_07_30_091523_create_system_settings_table', 1),
(25, '2026_07_30_091527_create_holidays_table', 1),
(26, '2026_07_30_091538_create_maintenance_schedules_table', 1),
(27, '2026_07_30_091545_create_system_logs_table', 1),
(28, '2026_07_30_091750_add_deleted_at_to_facility_requests_table', 1),
(29, '2026_07_30_091751_add_deleted_at_to_reservation_schedules_table', 1),
(30, '2026_07_30_091752_add_deleted_at_to_request_equipment_table', 1),
(31, '2026_07_30_091752_add_deleted_at_to_request_venues_table', 1),
(32, '2026_07_30_091753_add_deleted_at_to_request_status_histories_table', 1),
(33, '2026_07_30_091900_add_deleted_at_to_reservation_schedules_table', 1),
(34, '2026_07_30_120000_phase1_facility_requests_schema_cleanup', 1),
(35, '2026_07_30_130000_phase2_relational_facility_request_tables', 1),
(36, '2026_07_30_140000_create_reservation_schedules_table', 1),
(37, '2026_07_30_145000_migrate_existing_reservation_schedules', 1),
(38, '2026_07_31_000001_add_missing_soft_delete_columns', 1),
(39, '2026_07_31_000001_backfill_facility_request_relations', 1),
(40, '2026_08_02_000001_expand_facility_request_status_support', 1),
(41, '2026_08_02_000002_expand_user_role_support', 1),
(42, '2026_08_02_000003_remove_supply_office_role_values', 1),
(43, '2026_08_06_000001_add_venue_id_to_request_venues_table', 1),
(44, '2026_08_06_000001_create_reservation_reminder_logs_table', 1),
(45, '2026_08_06_000002_add_equipment_id_to_request_equipment_table', 1),
(46, '2026_08_06_000003_add_approved_by_id_foreign_to_facility_requests_table', 1),
(47, '2026_08_08_161548_create_sessions_table', 1),
(48, '2026_08_09_000001_add_authorized_custodian_ids_to_equipment_table', 1),
(49, '2026_08_09_000001_add_unique_key_to_reservation_reminder_logs_table', 1),
(50, '2026_08_10_144612_create_cache_table', 1),
(51, '2026_08_18_add_document_uploads_to_facility_requests', 1),
(52, '2026_08_18_create_colleges_table', 1),
(53, '2026_08_18_create_departments_table', 1),
(54, '2026_08_19_000001_add_request_profile_fields', 1),
(55, '2026_08_23_000001_add_active_status_to_resources', 1),
(56, '2026_08_23_000001_add_email_verification_to_users_table', 1),
(57, '2026_08_23_000002_create_password_reset_tokens_table', 1),
(58, '2026_08_23_000003_add_google_id_to_users_table', 1),
(59, '2026_08_23_000004_add_account_settings_to_users_table', 1),
(60, '2026_08_23_000005_add_student_organization_requestor_type', 1),
(61, '2026_08_23_000006_add_faculty_id_to_users_table', 1),
(62, '2026_08_24_000001_add_split_name_columns_to_users', 1),
(63, '2026_08_28_000001_add_cancelled_status_to_facility_requests', 1),
(64, '2026_08_28_000002_add_is_active_to_users_table', 1),
(65, '2026_08_28_000003_add_organization_name_to_facility_requests_table', 1),
(66, '2026_08_28_000004_add_organization_context_and_memberships', 1),
(67, '2026_08_28_000005_add_organization_metadata_and_membership_authority', 1),
(68, '2026_08_28_000006_create_revision_histories_table', 1),
(69, '2026_08_28_000007_add_priority_request_metadata_to_facility_requests', 1),
(70, '2026_08_29_000001_add_equipment_return_detail_fields_to_facility_requests_table', 1),
(71, '2026_08_30_000001_create_audit_logs_table', 1),
(72, '2026_09_01_000001_add_relational_context_to_student_organizations', 1),
(73, '2026_09_01_000002_restore_supply_office_role', 1),
(74, '2026_09_01_000003_migrate_student_organization_context_to_relational_ids', 1),
(75, '2026_09_02_155426_add_reservation_duration_to_facility_requests_table', 2),
(76, '2026_09_17_000001_create_request_change_requests_table', 3);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('073c1064-5c71-4eb1-ad1e-3afb31d5583d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":20,\"control_number\":\"FER-2026-3105\",\"activity\":\"assembly\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 17:30:29', '2026-09-29 17:30:29'),
('157547f3-98e8-4e77-93fa-c90da2de3d6b', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":20,\"control_number\":\"FER-2026-3105\",\"activity\":\"assembly\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 17:30:29', '2026-09-29 17:30:29'),
('1856d41d-95db-4b3b-9892-fe2a1f498a94', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 13, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"needs_revision\",\"message\":\"Your request status has been updated to Needs_revision. Control No: FER-2026-695\",\"route\":\"https:\\/\\/pitfr-rms.rf.gd\\/request\\/2\",\"notes\":\"Needs revision before final approval.\",\"actors\":{\"venue_custodian\":null,\"equipment_custodians\":[],\"supply_office\":null},\"return_status\":\"pending\",\"damaged_quantity\":0,\"missing_quantity\":0}', '2026-09-05 03:30:01', '2026-09-05 03:18:22', '2026-09-05 03:30:01'),
('1f0c35b7-7a00-4c1c-a4cb-77c628cfca8d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 11, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Assigned resource\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 16:50:32', '2026-09-08 16:50:32'),
('223cd046-9167-4f2c-8940-16e958707364', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-08 16:50:55', '2026-09-08 16:50:32', '2026-09-08 16:50:55'),
('228e50e6-42e6-4dff-97d5-d6ec3800e3c2', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":22,\"control_number\":\"FER-2026-2917\",\"activity\":\"event\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-30 15:03:03', '2026-09-30 15:03:03'),
('22ce13f4-3b47-4130-bdc4-29c687ea5a67', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":12,\"control_number\":\"FER-2026-6994\",\"activity\":\"vvvvvvvvvv\",\"status\":\"new_request\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-24 04:32:33', '2026-09-24 04:32:33'),
('29f5e6fd-74a9-4000-b3ef-dc18d99b91e0', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":9,\"control_number\":\"FER-2026-6405\",\"activity\":\"asdasdasd\",\"status\":\"new_request\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-18 03:54:27', '2026-09-18 03:54:27'),
('2c2240a6-44f8-48c2-b5b6-cffcf4559541', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":9,\"control_number\":\"FER-2026-6405\",\"activity\":\"asdasdasd\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-18 03:54:27', '2026-09-18 03:54:27'),
('2ee289a4-0f35-4ce8-854d-2efbfed8b069', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":15,\"control_number\":\"FER-2026-9278\",\"activity\":\"asdas\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 05:44:37', '2026-09-29 05:44:37'),
('372a047f-24dc-4b45-9a72-dccb56dd9ca2', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 16:50:32', '2026-09-08 16:50:32'),
('38471bc3-f3c4-4675-ba19-2af04dc99568', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":6,\"control_number\":\"FER-2026-8301\",\"activity\":\"MONEY LAUNDERING\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-15 16:02:43', '2026-09-15 16:02:43'),
('38a0ac9c-8cd4-4489-8bbc-424aa132a0fb', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 10, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Industrial Fans, Iwata Cooler Fans\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 16:51:15', '2026-09-08 16:51:15'),
('4a61a268-a933-45b8-9517-1416288cae81', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":22,\"control_number\":\"FER-2026-2917\",\"activity\":\"event\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-30 15:03:03', '2026-09-30 15:03:03'),
('51f8007d-c215-46e9-93de-8a2d1e8021dc', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', '2026-09-05 00:28:59', '2026-09-04 19:44:03', '2026-09-05 00:28:59'),
('5970aa50-7bc6-4253-9311-6b3825ccce8f', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-08 17:34:52', '2026-09-08 16:51:15', '2026-09-08 17:34:52'),
('598b15c9-b7e4-4db5-9d55-7ddbc95326ae', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":19,\"control_number\":\"FER-2026-033\",\"activity\":\"SDSD\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 16:48:07', '2026-09-29 16:48:07'),
('59abe07f-6325-4336-b48f-b6371272f778', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":21,\"control_number\":\"FER-2026-1653\",\"activity\":\"election\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"A new request is waiting for your verification.\"}', '2026-10-01 21:10:19', '2026-09-29 17:37:27', '2026-10-01 21:10:19'),
('5a5397e8-e834-41e9-ad36-e57a1f44f904', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":10,\"control_number\":\"FER-2026-4143\",\"activity\":\"asdasdas\",\"status\":\"new_request\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-23 18:11:55', '2026-09-23 18:11:55'),
('5a8d2297-af5f-4276-abcb-3562fa176ed5', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":5,\"control_number\":\"FER-2026-6168\",\"activity\":\"asdsad\",\"status\":\"new_request\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-14 22:36:50', '2026-09-14 22:34:56', '2026-09-14 22:36:50'),
('5f943c43-32ac-461a-b45d-a332808b2eb1', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":21,\"control_number\":\"FER-2026-1653\",\"activity\":\"election\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 17:37:27', '2026-09-29 17:37:27'),
('602a0c09-e53f-4f8f-a91e-6c436b583dbd', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":4,\"control_number\":\"FER-2026-835\",\"activity\":\"nsak\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 17:54:12', '2026-09-08 17:54:12'),
('6b365205-ee21-4bdd-8211-972f2eae2e61', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":19,\"control_number\":\"FER-2026-033\",\"activity\":\"SDSD\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 16:48:07', '2026-09-29 16:48:07'),
('6dfb1946-86f1-484f-8ba9-444ce04675a8', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":14,\"control_number\":\"FER-2026-8849\",\"activity\":\"Prime\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 05:39:20', '2026-09-29 05:39:20'),
('79f2eb2c-2756-4858-a0f4-491d2b8d80e1', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":20,\"control_number\":\"FER-2026-3105\",\"activity\":\"assembly\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 17:30:29', '2026-09-29 17:30:29'),
('7c69934f-75e4-4174-8cf0-87710075d6f3', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":12,\"control_number\":\"FER-2026-6994\",\"activity\":\"vvvvvvvvvv\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-24 04:32:33', '2026-09-24 04:32:33'),
('7f7d12fc-fcb3-4470-a8dc-ac19c7358fef', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 5, '{\"request_id\":8,\"control_number\":\"FER-2026-9574\",\"activity\":\"aasdas\",\"status\":\"new_request\",\"resource\":\"Balay Alumni\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-18 02:27:08', '2026-09-18 02:27:08'),
('830889c0-febe-442d-b55c-cd71936c3583', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":11,\"control_number\":\"FER-2026-5435\",\"activity\":\"asdad1231ewas\",\"status\":\"new_request\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-24 04:29:18', '2026-09-24 04:29:18'),
('850ba51d-986f-443b-899d-b3cc3fd6bd7e', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":10,\"control_number\":\"FER-2026-4143\",\"activity\":\"asdasdas\",\"status\":\"new_request\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-23 18:11:55', '2026-09-23 18:11:55'),
('8abcfcaa-167f-4864-9cae-f23d1444904b', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":7,\"control_number\":\"FER-2026-9792\",\"activity\":\"asdasd\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-15 17:02:30', '2026-09-15 17:02:30'),
('8c307e50-52c5-402d-aa38-38bd786ab670', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":17,\"control_number\":\"FER-2026-1685\",\"activity\":\"Experimental\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 13:24:09', '2026-09-29 13:24:09'),
('8d09d77f-f502-4fba-b080-c393a9481938', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"new_request\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-08 17:43:57', '2026-09-05 03:30:30', '2026-09-08 17:43:57'),
('8eb6e6c3-5e5d-44ad-8bc6-4a31da4d11dc', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":14,\"control_number\":\"FER-2026-8849\",\"activity\":\"Prime\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 05:39:20', '2026-09-29 05:39:20'),
('9045b62b-2ae4-473e-a6a7-d6ace5fc8daf', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":10,\"control_number\":\"FER-2026-4143\",\"activity\":\"asdasdas\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-23 18:11:55', '2026-09-23 18:11:55'),
('9201f1b9-adef-4f4b-be9e-a6da84dbfc14', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":17,\"control_number\":\"FER-2026-1685\",\"activity\":\"Experimental\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 13:24:09', '2026-09-29 13:24:09'),
('933818fa-9006-4a15-85f7-528e63bd9a69', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":15,\"control_number\":\"FER-2026-9278\",\"activity\":\"asdas\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 05:44:37', '2026-09-29 05:44:37'),
('a771f363-69bd-4747-9a8a-06ad2ccb6c1f', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":7,\"control_number\":\"FER-2026-9792\",\"activity\":\"asdasd\",\"status\":\"new_request\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-15 17:02:30', '2026-09-15 17:02:30'),
('a913588e-d5d5-4d45-9e8a-c9ce896cc290', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":18,\"control_number\":\"FER-2026-6633\",\"activity\":\"OJT Orientation\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 15:36:43', '2026-09-29 15:36:43'),
('aaa0481e-508f-4344-a037-6acdf035ea97', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":16,\"control_number\":\"FER-2026-5318\",\"activity\":\"Open Forum\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 06:07:02', '2026-09-29 06:07:02'),
('ac91279b-7601-4c86-a9c1-79df0c195a78', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":4,\"control_number\":\"FER-2026-835\",\"activity\":\"nsak\",\"status\":\"new_request\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 17:54:12', '2026-09-08 17:54:12'),
('b123fbab-c7b3-4416-acd1-4b171f4a4fac', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 16:51:15', '2026-09-08 16:51:15'),
('b7f70807-f8ed-43b6-a2d6-8bc5de3bac71', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 11, '{\"request_id\":16,\"control_number\":\"FER-2026-5318\",\"activity\":\"Open Forum\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Assigned resource\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 06:07:02', '2026-09-29 06:07:02'),
('b9607f42-c803-49c0-a7d6-c12bdfe3bb36', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 5, '{\"request_id\":13,\"control_number\":\"FER-2026-6954\",\"activity\":\"NORP\",\"status\":\"new_request\",\"resource\":\"Balay Alumni\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-25 02:35:12', '2026-09-24 05:24:07', '2026-09-25 02:35:12');
INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('ba7735e6-aacb-43cf-91fe-7972377c990e', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":18,\"control_number\":\"FER-2026-6633\",\"activity\":\"OJT Orientation\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 15:36:43', '2026-09-29 15:36:43'),
('bd357e19-72e1-4d56-b1a0-6ba66c537e6d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":11,\"control_number\":\"FER-2026-5435\",\"activity\":\"asdad1231ewas\",\"status\":\"new_request\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-24 04:29:18', '2026-09-24 04:29:18'),
('bd35f05b-f0e4-4e3f-86b1-8e9ac2241d4b', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', '2026-09-05 00:43:20', '2026-09-04 19:44:03', '2026-09-05 00:43:20'),
('bdff0605-cfa3-484c-ba1a-199d2668a82d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":21,\"control_number\":\"FER-2026-1653\",\"activity\":\"election\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 17:37:27', '2026-09-29 17:37:27'),
('c18132ab-1733-40db-90a8-3df0d8a9d74d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 10, '{\"request_id\":16,\"control_number\":\"FER-2026-5318\",\"activity\":\"Open Forum\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Industrial Fans, Iwata Cooler Fans\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 06:07:02', '2026-09-29 06:07:02'),
('c1f6d28b-aacc-47e9-81be-97f069b242a0', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":16,\"control_number\":\"FER-2026-5318\",\"activity\":\"Open Forum\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 06:07:02', '2026-09-29 06:07:02'),
('cf18a235-0828-4b0a-86b6-3e86c0a506e4', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":9,\"control_number\":\"FER-2026-6405\",\"activity\":\"asdasdasd\",\"status\":\"new_request\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-22 03:57:53', '2026-09-18 03:54:27', '2026-09-22 03:57:53'),
('d9c40701-61cc-470b-9c14-cd8cd3324542', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 13, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"venue_approved\",\"message\":\"Your request status has been updated to Venue_approved. Control No: FER-2026-695\",\"route\":\"https:\\/\\/pitfr-rms.rf.gd\\/request\\/2\",\"notes\":\"\",\"actors\":{\"venue_custodian\":null,\"equipment_custodians\":[],\"supply_office\":null},\"return_status\":\"pending\",\"damaged_quantity\":0,\"missing_quantity\":0}', '2026-09-05 03:03:33', '2026-09-05 00:29:29', '2026-09-05 03:03:33'),
('da791be2-eadd-406e-b362-81174c432c6b', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-05 03:30:30', '2026-09-05 03:30:30'),
('ddbd3ed6-5f70-43c8-9b27-7d8d4ff94206', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":6,\"control_number\":\"FER-2026-8301\",\"activity\":\"MONEY LAUNDERING\",\"status\":\"new_request\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-15 16:02:43', '2026-09-15 16:02:43'),
('df3a6076-c578-4814-a72a-53b82798ee10', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"new_request\",\"resource\":\"Conference Hall & Interaction Center (CHIC)\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-08 17:43:57', '2026-09-08 16:46:06', '2026-09-08 17:43:57'),
('e0e257c0-7259-41b6-b495-14b0abcc800a', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 10, '{\"request_id\":3,\"control_number\":\"FER-2026-9911\",\"activity\":\"grand\",\"status\":\"new_request\",\"resource\":\"Industrial Fans, Iwata Cooler Fans\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 16:50:32', '2026-09-08 16:50:32'),
('e3cec282-fe64-4295-835f-919253dcd01d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":11,\"control_number\":\"FER-2026-5435\",\"activity\":\"asdad1231ewas\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-24 04:29:18', '2026-09-24 04:29:18'),
('e65a46eb-95f2-4444-8c89-420c1ebf1244', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 10, '{\"request_id\":18,\"control_number\":\"FER-2026-6633\",\"activity\":\"OJT Orientation\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Industrial Fans, Iwata Cooler Fans\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 15:36:43', '2026-09-29 15:36:43'),
('ea888f5e-b087-4533-9201-718201fa6caa', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":2,\"control_number\":\"FER-2026-695\",\"activity\":\"Mad Dogg\'s Party Homie\",\"status\":\"new_request\",\"resource\":\"Sound System, Wireless Microphones, Non-Wireless Microphones\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-08 16:46:06', '2026-09-08 16:46:06'),
('ecc11827-9132-4a5d-96be-f37ea6ff31c1', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":16,\"control_number\":\"FER-2026-5318\",\"activity\":\"Open Forum\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 06:07:02', '2026-09-29 06:07:02'),
('ee58dfd7-b0d7-413d-9055-2a91464a2590', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":12,\"control_number\":\"FER-2026-6994\",\"activity\":\"vvvvvvvvvv\",\"status\":\"new_request\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"This request is waiting for your verification.\"}', NULL, '2026-09-24 04:32:33', '2026-09-24 04:32:33'),
('f0e341ed-2735-4bed-b8cb-2f05e4832e4d', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 11, '{\"request_id\":18,\"control_number\":\"FER-2026-6633\",\"activity\":\"OJT Orientation\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Assigned resource\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 15:36:43', '2026-09-29 15:36:43'),
('f4441b02-b07d-4f4a-800b-f247fef6ba4a', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":18,\"control_number\":\"FER-2026-6633\",\"activity\":\"OJT Orientation\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 15:36:43', '2026-09-29 15:36:43'),
('f5c33a0d-3726-4e5d-9912-db59de81bd98', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 5, '{\"request_id\":14,\"control_number\":\"FER-2026-8849\",\"activity\":\"Prime\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Balay Alumni\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-29 05:39:20', '2026-09-29 05:39:20'),
('f5cc0896-167d-425d-9f43-a54d55db9c43', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":7,\"control_number\":\"FER-2026-9792\",\"activity\":\"asdasd\",\"status\":\"new_request\",\"resource\":\"Canopies, Tables, Monobloc Chairs\",\"message\":\"This request is waiting for your verification.\"}', '2026-09-22 03:58:04', '2026-09-15 17:02:30', '2026-09-22 03:58:04'),
('fb53405f-0f6b-4577-b9f6-8e3751f87d2e', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":22,\"control_number\":\"FER-2026-2917\",\"activity\":\"event\",\"status\":\"new_request\",\"title\":\"New Request Submitted\",\"body\":\"A new request is waiting for your verification.\",\"resource\":\"Gymnasium, Oval Grounds, Covered Court, Volleyball Court\",\"message\":\"A new request is waiting for your verification.\"}', NULL, '2026-09-30 15:03:03', '2026-09-30 15:03:03'),
('fc224680-4539-4ae4-b8e4-ddb1d6b44419', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":1,\"control_number\":\"FER-2026-9000\",\"activity\":\"asdsadasda\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', '2026-09-05 00:43:20', '2026-09-04 19:01:53', '2026-09-05 00:43:20');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`email`, `token`, `created_at`) VALUES
('andalesnick@gmail.com', '$2y$12$S3IgJgiJXHMZPtZAwsCGYOyXVeS5OiM92agi3mQxmxpKElAqifZgO', '2026-09-14 16:21:16'),
('ephraimbetoy2@gmail.com', '$2y$12$Id0vj5cMzZD8XAl07NAur.FxenFFwPddau2C8xd/mINm.Bxu2plvG', '2026-09-20 06:21:09'),
('grovestreetfam@gmail.com', '$2y$12$6KLZz49rz2q5vJTtG66FLe0aL80iRSlZr9w/Aw/RukKKnaqb4P8oa', '2026-09-24 04:55:01'),
('krukawa1103@gmail.com', '$2y$12$xEZlP9ijfe8alzn2REVo3eygEB.XHrFaTqom7Uh0qM/5IqzuJZkIW', '2026-09-29 02:10:07');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_change_requests`
--

CREATE TABLE `request_change_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `requested_by_id` bigint(20) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `decided_by_id` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_equipment`
--

CREATE TABLE `request_equipment` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `equipment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_equipment`
--

INSERT INTO `request_equipment` (`id`, `facility_request_id`, `equipment_id`, `name`, `quantity`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 1, 1, 'Sound System', 1, '2026-09-04 19:01:53', '2026-09-04 19:01:53', NULL),
(6, 2, 1, 'Sound System', 1, '2026-09-08 16:46:06', '2026-09-08 16:46:06', NULL),
(15, 3, 1, 'Sound System', 1, '2026-09-08 16:51:15', '2026-09-08 16:51:15', NULL),
(16, 3, 2, 'Wireless Microphones', 1, '2026-09-08 16:51:15', '2026-09-08 16:51:15', NULL),
(17, 3, 5, 'Industrial Fans', 3, '2026-09-08 16:51:15', '2026-09-08 16:51:15', NULL),
(18, 3, 6, 'Iwata Cooler Fans', 2, '2026-09-08 16:51:15', '2026-09-08 16:51:15', NULL),
(20, 4, 1, 'Sound System', 1, '2026-09-08 17:54:12', '2026-09-08 17:54:12', NULL),
(27, 5, 1, 'Sound System', 1, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(28, 5, 2, 'Wireless Microphones', 1, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(29, 5, 3, 'Non-Wireless Microphones', 1, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(30, 5, 7, 'Tables', 1, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(31, 5, 8, 'Monobloc Chairs', 1, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(32, 5, NULL, 'Aircon', 1, '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(35, 6, 1, 'Sound System', 1, '2026-09-15 16:02:43', '2026-09-15 16:02:43', NULL),
(36, 6, 2, 'Wireless Microphones', 1, '2026-09-15 16:02:43', '2026-09-15 16:02:43', NULL),
(42, 7, 1, 'Sound System', 1, '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(43, 7, 2, 'Wireless Microphones', 1, '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(44, 7, 3, 'Non-Wireless Microphones', 1, '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(45, 7, 7, 'Tables', 1, '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(46, 7, 8, 'Monobloc Chairs', 1, '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(53, 8, 1, 'Sound System', 1, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(54, 8, 2, 'Wireless Microphones', 1, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(55, 8, 3, 'Non-Wireless Microphones', 1, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(56, 8, 7, 'Tables', 1, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(57, 8, NULL, 'Aircon', 1, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(58, 8, NULL, 'Chairs', 1, '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(63, 9, 1, 'Sound System', 1, '2026-09-18 03:54:27', '2026-09-18 03:54:27', NULL),
(64, 9, 2, 'Wireless Microphones', 1, '2026-09-18 03:54:27', '2026-09-18 03:54:27', NULL),
(65, 9, 3, 'Non-Wireless Microphones', 1, '2026-09-18 03:54:27', '2026-09-18 03:54:27', NULL),
(66, 9, 7, 'Tables', 1, '2026-09-18 03:54:27', '2026-09-18 03:54:27', NULL),
(71, 10, 1, 'Sound System', 1, '2026-09-23 18:11:55', '2026-09-23 18:11:55', NULL),
(72, 10, 2, 'Wireless Microphones', 1, '2026-09-23 18:11:55', '2026-09-23 18:11:55', NULL),
(73, 10, 3, 'Non-Wireless Microphones', 1, '2026-09-23 18:11:55', '2026-09-23 18:11:55', NULL),
(74, 10, 7, 'Tables', 1, '2026-09-23 18:11:55', '2026-09-23 18:11:55', NULL),
(79, 11, 1, 'Sound System', 1, '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL),
(80, 11, 2, 'Wireless Microphones', 1, '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL),
(81, 11, 3, 'Non-Wireless Microphones', 1, '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL),
(82, 11, 7, 'Tables', 1, '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL),
(87, 12, 1, 'Sound System', 1, '2026-09-24 04:32:33', '2026-09-24 04:32:33', NULL),
(88, 12, 2, 'Wireless Microphones', 1, '2026-09-24 04:32:33', '2026-09-24 04:32:33', NULL),
(89, 12, 3, 'Non-Wireless Microphones', 1, '2026-09-24 04:32:33', '2026-09-24 04:32:33', NULL),
(90, 12, 7, 'Tables', 1, '2026-09-24 04:32:33', '2026-09-24 04:32:33', NULL),
(97, 13, 1, 'Sound System', 1, '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(98, 13, 2, 'Wireless Microphones', 1, '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(99, 13, 3, 'Non-Wireless Microphones', 1, '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(100, 13, 7, 'Tables', 1, '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(101, 13, NULL, 'Aircon', 1, '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(102, 13, NULL, 'Chairs', 1, '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(106, 14, 1, 'Sound System', 1, '2026-09-29 05:39:20', '2026-09-29 05:39:20', NULL),
(107, 14, 2, 'Wireless Microphones', 1, '2026-09-29 05:39:20', '2026-09-29 05:39:20', NULL),
(108, 14, 8, 'Monobloc Chairs', 130, '2026-09-29 05:39:20', '2026-09-29 05:39:20', NULL),
(110, 15, 1, 'Sound System', 1, '2026-09-29 05:44:37', '2026-09-29 05:44:37', NULL),
(115, 16, 1, 'Sound System', 1, '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL),
(116, 16, 2, 'Wireless Microphones', 1, '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL),
(117, 16, 6, 'Iwata Cooler Fans', 4, '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL),
(118, 16, 8, 'Monobloc Chairs', 160, '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL),
(120, 17, 7, 'Tables', 1, '2026-09-29 13:24:09', '2026-09-29 13:24:09', NULL),
(126, 18, 1, 'Sound System', 1, '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(127, 18, 2, 'Wireless Microphones', 1, '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(128, 18, 5, 'Industrial Fans', 4, '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(129, 18, 6, 'Iwata Cooler Fans', 2, '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(130, 18, 8, 'Monobloc Chairs', 100, '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(133, 19, 1, 'Sound System', 1, '2026-09-29 16:48:07', '2026-09-29 16:48:07', NULL),
(134, 19, 2, 'Wireless Microphones', 1, '2026-09-29 16:48:07', '2026-09-29 16:48:07', NULL),
(139, 20, 1, 'Sound System', 1, '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL),
(140, 20, 2, 'Wireless Microphones', 1, '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL),
(141, 20, 7, 'Tables', 4, '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL),
(142, 20, 8, 'Monobloc Chairs', 75, '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL),
(146, 21, 1, 'Sound System', 1, '2026-09-29 17:37:27', '2026-09-29 17:37:27', NULL),
(147, 21, 2, 'Wireless Microphones', 1, '2026-09-29 17:37:27', '2026-09-29 17:37:27', NULL),
(148, 21, 8, 'Monobloc Chairs', 80, '2026-09-29 17:37:27', '2026-09-29 17:37:27', NULL),
(154, 22, 1, 'Sound System', 1, '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL),
(155, 22, 2, 'Wireless Microphones', 1, '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL),
(156, 22, 3, 'Non-Wireless Microphones', 1, '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL),
(157, 22, 7, 'Tables', 1, '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL),
(158, 22, 8, 'Monobloc Chairs', 1, '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `request_histories`
--

CREATE TABLE `request_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `detail` text DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_histories`
--

INSERT INTO `request_histories` (`id`, `facility_request_id`, `user_id`, `action`, `detail`, `occurred_at`, `created_at`, `updated_at`) VALUES
(1, 1, 13, 'cancelled', 'Request cancelled by requester Nick Vincent Degamo Andales N/A', '2026-09-04 22:54:45', '2026-09-04 22:54:45', '2026-09-04 22:54:45'),
(2, 2, 6, 'custodian_endorsed', 'Venue request verified and endorsed by ARLENE L. SALA', '2026-09-05 00:29:27', '2026-09-05 00:29:27', '2026-09-05 00:29:27'),
(3, 2, 8, 'custodian_endorsed', 'Equipment request verified and endorsed by ROGELIO GUILLEMER', '2026-09-05 00:44:04', '2026-09-05 00:44:04', '2026-09-05 00:44:04'),
(4, 2, 12, 'needs_revision', 'Needs Revision decision recorded by Supply Office: Needs revision before final approval.', '2026-09-05 03:18:20', '2026-09-05 03:18:20', '2026-09-05 03:18:20'),
(5, 2, NULL, 'needs_reschedule', 'Reservation rescheduled by requestor after Priority Override.', '2026-09-05 03:30:30', '2026-09-05 03:30:30', '2026-09-05 03:30:30'),
(6, 9, 9, 'equipment_status_rejected', 'Request rejected by JAIME SURALTA', '2026-09-22 04:05:22', '2026-09-22 04:05:22', '2026-09-22 04:05:22'),
(7, 13, 5, 'venue_status_rejected', 'Request rejected by MILDRED P. MERCADO', '2026-09-25 02:35:21', '2026-09-25 02:35:21', '2026-09-25 02:35:21'),
(8, 12, 12, 'final_rejected', 'Final decline issued by Administrator', '2026-09-27 20:57:27', '2026-09-27 20:57:27', '2026-09-27 20:57:27'),
(9, 17, 18, 'cancelled', 'Request cancelled by requester Example Test Account', '2026-09-29 13:35:25', '2026-09-29 13:35:25', '2026-09-29 13:35:25');

-- --------------------------------------------------------

--
-- Table structure for table `request_status_history`
--

CREATE TABLE `request_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_venues`
--

CREATE TABLE `request_venues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `venue_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_venues`
--

INSERT INTO `request_venues` (`id`, `facility_request_id`, `venue_id`, `name`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 1, NULL, 'Balay Alumni', '2026-09-04 19:01:53', '2026-09-04 19:01:53', NULL),
(6, 2, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-08 16:46:06', '2026-09-08 16:46:06', NULL),
(9, 3, 4, 'Oval Grounds', '2026-09-08 16:51:15', '2026-09-08 16:51:15', NULL),
(11, 4, 4, 'Oval Grounds', '2026-09-08 17:54:12', '2026-09-08 17:54:12', NULL),
(13, 5, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(15, 6, 5, 'Covered Court', '2026-09-15 16:02:43', '2026-09-15 16:02:43', NULL),
(17, 7, 5, 'Covered Court', '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(19, 8, 3, 'Balay Alumni', '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(21, 9, 5, 'Covered Court', '2026-09-18 03:54:27', '2026-09-18 03:54:27', NULL),
(23, 10, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-23 18:11:55', '2026-09-23 18:11:55', NULL),
(25, 11, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL),
(27, 12, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-24 04:32:33', '2026-09-24 04:32:33', NULL),
(29, 13, 3, 'Balay Alumni', '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(31, 14, 3, 'Balay Alumni', '2026-09-29 05:39:20', '2026-09-29 05:39:20', NULL),
(33, 15, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-29 05:44:37', '2026-09-29 05:44:37', NULL),
(35, 16, 5, 'Covered Court', '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL),
(37, 17, 2, 'Gymnasium', '2026-09-29 13:24:09', '2026-09-29 13:24:09', NULL),
(39, 18, 5, 'Covered Court', '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(41, 19, 6, 'Volleyball Court', '2026-09-29 16:48:07', '2026-09-29 16:48:07', NULL),
(43, 20, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL),
(45, 21, 1, 'Conference Hall & Interaction Center (CHIC)', '2026-09-29 17:37:27', '2026-09-29 17:37:27', NULL),
(47, 22, 2, 'Gymnasium', '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_reminder_logs`
--

CREATE TABLE `reservation_reminder_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `reminder_type` varchar(255) NOT NULL,
  `scheduled_for` datetime NOT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_schedules`
--

CREATE TABLE `reservation_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservation_schedules`
--

INSERT INTO `reservation_schedules` (`id`, `facility_request_id`, `start_datetime`, `end_datetime`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 1, '2026-09-07 08:00:00', '2026-09-11 23:59:00', '2026-09-04 19:01:53', '2026-09-04 19:01:53', NULL),
(6, 2, '2026-09-15 08:00:00', '2026-09-15 23:59:00', '2026-09-08 16:46:06', '2026-09-08 16:46:06', NULL),
(9, 3, '2026-09-08 08:00:00', '2026-09-08 12:00:00', '2026-09-08 16:51:15', '2026-09-08 16:51:15', NULL),
(11, 4, '2026-09-08 08:00:00', '2026-09-08 12:00:00', '2026-09-08 17:54:12', '2026-09-08 17:54:12', NULL),
(13, 5, '2026-09-14 08:00:00', '2026-09-14 23:59:00', '2026-09-14 22:34:56', '2026-09-14 22:34:56', NULL),
(15, 6, '2026-09-15 08:00:00', '2026-09-15 23:59:00', '2026-09-15 16:02:43', '2026-09-15 16:02:43', NULL),
(17, 7, '2026-09-15 08:00:00', '2026-09-15 23:59:00', '2026-09-15 17:02:30', '2026-09-15 17:02:30', NULL),
(19, 8, '2026-09-18 08:00:00', '2026-09-18 23:59:00', '2026-09-18 02:27:08', '2026-09-18 02:27:08', NULL),
(21, 9, '2026-09-19 08:00:00', '2026-09-19 12:00:00', '2026-09-18 03:54:27', '2026-09-18 03:54:27', NULL),
(23, 10, '2026-09-23 08:00:00', '2026-09-23 23:59:00', '2026-09-23 18:11:55', '2026-09-23 18:11:55', NULL),
(25, 11, '2026-09-23 08:00:00', '2026-09-23 23:59:00', '2026-09-24 04:29:17', '2026-09-24 04:29:17', NULL),
(27, 12, '2026-09-23 08:00:00', '2026-09-23 23:59:00', '2026-09-24 04:32:33', '2026-09-24 04:32:33', NULL),
(29, 13, '2026-09-24 08:00:00', '2026-09-24 23:59:00', '2026-09-24 05:24:07', '2026-09-24 05:24:07', NULL),
(31, 14, '2026-09-30 08:00:00', '2026-09-30 12:00:00', '2026-09-29 05:39:20', '2026-09-29 05:39:20', NULL),
(33, 15, '2026-09-29 08:00:00', '2026-09-29 23:59:00', '2026-09-29 05:44:37', '2026-09-29 05:44:37', NULL),
(35, 16, '2026-09-30 13:00:00', '2026-09-30 16:00:00', '2026-09-29 06:07:02', '2026-09-29 06:07:02', NULL),
(37, 17, '2026-09-29 08:00:00', '2026-09-30 23:59:00', '2026-09-29 13:24:09', '2026-09-29 13:24:09', NULL),
(39, 18, '2026-09-30 08:00:00', '2026-09-30 12:00:00', '2026-09-29 15:36:43', '2026-09-29 15:36:43', NULL),
(41, 19, '2026-09-30 08:00:00', '2026-09-30 23:59:00', '2026-09-29 16:48:07', '2026-09-29 16:48:07', NULL),
(43, 20, '2026-09-29 13:00:00', '2026-09-29 17:00:00', '2026-09-29 17:30:29', '2026-09-29 17:30:29', NULL),
(45, 21, '2026-09-29 13:00:00', '2026-09-29 17:00:00', '2026-09-29 17:37:27', '2026-09-29 17:37:27', NULL),
(47, 22, '2026-09-30 08:00:00', '2026-10-01 23:59:00', '2026-09-30 15:03:03', '2026-09-30 15:03:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `revision_histories`
--

CREATE TABLE `revision_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_request_id` bigint(20) UNSIGNED NOT NULL,
  `revised_by_id` bigint(20) UNSIGNED NOT NULL,
  `old_start_date` date DEFAULT NULL,
  `old_end_date` date DEFAULT NULL,
  `old_start_time` varchar(20) DEFAULT NULL,
  `old_end_time` varchar(20) DEFAULT NULL,
  `old_venue` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_organizations`
--

CREATE TABLE `student_organizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `college_id` bigint(20) UNSIGNED DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `acronym` varchar(50) DEFAULT NULL,
  `organization_type` varchar(100) DEFAULT NULL,
  `adviser` varchar(191) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_organizations`
--

INSERT INTO `student_organizations` (`id`, `name`, `college_id`, `department_id`, `category`, `is_active`, `created_at`, `updated_at`, `acronym`, `organization_type`, `adviser`) VALUES
(1, 'Bonded Information Technology Students (BITS)', 6, 21, 'COTE', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'BITS', 'Academic', NULL),
(2, 'Junior Philippine Institute of Industrial Engineers (JPIIE)', 6, 24, 'COTE', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'JPIIE', 'Academic', NULL),
(3, 'Institute of Integrated Electrical Engineers (IIEE)', 6, 22, 'COTE', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'IIEE', 'Academic', NULL),
(4, 'Industrial Developers of the Land (IDOL)', 6, 25, 'COTE', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'IDOL', 'Academic', NULL),
(5, 'Junior Philippine Society of Mechanical Engineers (JPSME)', 6, 23, 'COTE', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'JPSME', 'Academic', NULL),
(6, 'Vital Organization of Intellectual Communicators and Eloquent Speakers (VOICES)', 9, 36, 'CAS', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'VOICES', 'Academic', NULL),
(7, 'Students Association of Restaurateurs, Hoteliers and International Professional Seafarers (STARSHIPS)', 9, 38, 'CAS', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'STARSHIPS', 'Academic', NULL),
(8, 'Marketers Organization', 9, 35, 'CAS', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'MO', 'Academic', NULL),
(9, 'Marine Trident', 9, 37, 'CAS', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'MT', 'Academic', NULL),
(10, 'Seekers of Adventure Inspired by the Love of the Sea (SAILS)', 8, 31, 'COMED', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'SAILS', 'Academic', NULL),
(11, 'Association of Marine Engineering Students of PIT (AMESOP)', 8, 30, 'COMED', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'AMESOP', 'Academic', NULL),
(12, 'Future Educators Organization', 7, 26, 'CTE', 1, '2026-09-01 11:03:39', '2026-09-01 11:03:39', 'FEO', 'Academic', NULL),
(13, 'Supreme Student Government (SSG)', NULL, NULL, 'Mandated College', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'SSG', 'Student Organization', NULL),
(14, 'Publication Fulcrum', NULL, NULL, 'Mandated College', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(15, 'Supreme Student Council (SSC)', NULL, NULL, 'Mandated Highschool', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'SSC', 'Student Organization', NULL),
(16, 'The Builder', NULL, NULL, 'Mandated Highschool', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(17, 'Molders of Young Minds (MOYM)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'MOYM', 'Student Organization', NULL),
(18, 'Society of Healthy and Active Physical Educators (SHAPE)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'SHAPE', 'Student Organization', NULL),
(19, 'Organization of Aspiring Critical Language Educators (ORACLE)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'ORACLE', 'Student Organization', NULL),
(20, 'Figure Enthusiast (FE)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'FE', 'Student Organization', NULL),
(21, 'Kapisanang Filipino (KAFIL)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'KAFIL', 'Student Organization', NULL),
(22, 'Movement of Social Thinkers (MOST)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'MOST', 'Student Organization', NULL),
(23, 'Technology Educators Organization (TECHEDO)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'TECHEDO', 'Student Organization', NULL),
(24, 'Graduating Educators\' Organization (GEO)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'GEO', 'Student Organization', NULL),
(25, 'Alliance of Intellectually Molded Scholars (AIMS)', NULL, NULL, 'Academic Related', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'AIMS', 'Student Organization', NULL),
(26, 'Kristiyanong Kabataan Para sa Bayan (KKB-PIT)', NULL, NULL, 'Religious Activities', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'KKB-PIT', 'Student Organization', NULL),
(27, 'The Enfolders', NULL, NULL, 'Religious Activities', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(28, 'PIT Campus Ministry', NULL, NULL, 'Religious Activities', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(29, 'PIT Esports', NULL, NULL, 'Sports', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(30, 'Absolute Focus', NULL, NULL, 'Sports', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(31, 'PIT Arnis Association (PITAA)', NULL, NULL, 'Sports', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'PITAA', 'Student Organization', NULL),
(32, 'PIT Taekwondo Association (PITTA)', NULL, NULL, 'Sports', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'PITTA', 'Student Organization', NULL),
(33, 'Kaapit Sayaw', NULL, NULL, 'Cultural Affairs/Performing Arts', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(34, 'Mugna', NULL, NULL, 'Cultural Affairs/Performing Arts', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', NULL, 'Student Organization', NULL),
(35, 'PIT-LHS Drums, Bugle, and Lyre Corps. (PIT-LHS DBLC)', NULL, NULL, 'Cultural Affairs/Performing Arts', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'PIT-LHS DBLC', 'Student Organization', NULL),
(36, 'Students Active Volunteers Emergency Responders - Red Cross Youth Council PIT Chapter (SAVERS-RCYC-PIT)', NULL, NULL, 'Emergency Response', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'SAVERS-RCYC-PIT', 'Student Organization', NULL),
(37, 'Alpha Phi Omega - Eta Pi Chapter (APO)', NULL, NULL, 'Emergency Response', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'APO', 'Student Organization', NULL),
(38, 'Association of Student Assistant Program (ASAP)', NULL, NULL, 'Emergency Response', 1, '2026-09-20 14:51:56', '2026-09-20 14:51:56', 'ASAP', 'Student Organization', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_organization_members`
--

CREATE TABLE `student_organization_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `student_organization_id` bigint(20) UNSIGNED NOT NULL,
  `membership_role` varchar(100) DEFAULT NULL,
  `can_submit_requests` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_organization_members`
--

INSERT INTO `student_organization_members` (`id`, `user_id`, `student_organization_id`, `membership_role`, `can_submit_requests`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 13, 1, 'Member', 1, 1, '2026-09-02 14:26:32', '2026-09-02 14:26:32'),
(2, 17, 14, 'Adviser', 0, 1, '2026-09-24 04:55:01', '2026-09-24 04:55:01'),
(3, 18, 17, 'Member', 1, 1, '2026-09-29 02:10:06', '2026-09-29 02:10:06');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `log_name` varchar(255) NOT NULL DEFAULT 'default',
  `event` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `causer_type` varchar(255) DEFAULT NULL,
  `causer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `e_signature_file` varchar(255) DEFAULT NULL,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` timestamp NULL DEFAULT NULL,
  `otp_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `otp_last_sent_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `college_id` bigint(20) UNSIGNED DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` enum('student','faculty','requestor','custodian','admin') NOT NULL DEFAULT 'requestor',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `requestor_type` enum('student','faculty','outsider','student_organization') DEFAULT NULL,
  `school_id_number` varchar(50) DEFAULT NULL,
  `faculty_id` varchar(50) DEFAULT NULL,
  `office_or_organization` varchar(191) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `e_signature_file`, `notification_preferences`, `google_id`, `email_verified_at`, `otp_hash`, `otp_expires_at`, `otp_attempts`, `otp_last_sent_at`, `password`, `name`, `surname`, `first_name`, `middle_name`, `suffix`, `department`, `college_id`, `department_id`, `position`, `role`, `is_active`, `requestor_type`, `school_id_number`, `faculty_id`, `office_or_organization`, `contact_number`, `remember_token`, `created_at`, `updated_at`) VALUES
(3, 'student1@gmail.com', NULL, NULL, NULL, NULL, '$2y$12$7..utCCDcnGnH2U69mg.YOpIIqvS00.uTbW55ifnyJVCE4nFyyeoK', '2026-09-21 16:14:25', 0, '2026-09-21 16:04:25', '$2y$12$9YPTW75ORYP1gGKvxIdsd.ZDfXtVSicjlOve3Vge9rzePolLp6GA.', 'BITS ORG', 'ORG', 'BITS', NULL, NULL, NULL, NULL, NULL, NULL, 'requestor', 1, 'student', NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-21 16:04:25'),
(4, 'faculty1@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$2zRTiy1kBXIijBFScBuIhuh8Zw4JKGL/BoaaLw4tOkIalygxJqtp.', 'IT Department', 'Department', 'IT', NULL, NULL, NULL, NULL, NULL, NULL, 'requestor', 1, 'faculty', NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(5, 'mmercado@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$GqGsyR5d/8kEhh1QqNfD1.hc4Pw95C3vH4YDi2ic6mT5dSu97p5Ki', 'MILDRED P. MERCADO', 'MERCADO', 'MILDRED', 'P.', NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(6, 'asala@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$nqQhgmiKJXgM2zmpRc2A7.GgnLovwxSEMowfDs1D25XfzXgP8ZZG.', 'ARLENE L. SALA', 'SALA', 'ARLENE', 'L.', NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(7, 'ctado@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$XiFasNdEYSncXEqRLISeAucyrhxxbdnUP0SHScDR14e7.cKw1I8au', 'CHARLES ROMMEL L. TADO', 'TADO', 'CHARLES ROMMEL', 'L.', NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(8, 'rguillemer@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$Z4CZOrmSHrsuh/xn3yGZnuRkx.W6vQNFW.VpHcZjFachkxuBa.LDC', 'ROGELIO GUILLEMER', 'GUILLEMER', 'ROGELIO', NULL, NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(9, 'jsuralta@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$UX2lTUizxMdnthMIptHgmOKJekNZzGdXHuGpzTyZL6CE6tOkHOIkS', 'JAIME SURALTA', 'SURALTA', 'JAIME', NULL, NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(10, 'lalmerino@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$5dF8Yyfwp45SJQLXusC.fOlnWI1rlJENMB56VBJF9UZ6.oWbg9tRu', 'LEOGEL ALMERINO', 'ALMERINO', 'LEOGEL', NULL, NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(11, 'jrvillas@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$eZv1AjpBwdKxmJjtn2Z1Tuug86YnoGEyVJuLYobosKSusFi17XPK.', 'RICHARD VILLAS', 'VILLAS', 'RICHARD', NULL, NULL, NULL, NULL, NULL, NULL, 'custodian', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(12, 'admin', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '$2y$12$y2VZHn/qhnAZxEjsYE5xKu5cQ8IaShb/hzmUHZFuQSIloFhl1iWAm', 'Administrator', 'Administrator', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(13, 'andalesnick@gmail.com', '13_20260912204631.png', NULL, NULL, '2026-09-02 14:26:32', NULL, NULL, 0, NULL, '$2y$12$hCyf80s0IBDcztMrcoCXEuWT6nqGNgi3VycfOM7BQnGFvWr4UioFq', 'Nick Vincent Degamo Andales N/A', 'Andales', 'Nick Vincent', 'Degamo', 'N/A', 'Information Technology', 6, 21, 'Vice President - External', 'requestor', 1, 'student', '23-0098-635', NULL, NULL, '09553494424', NULL, '2026-09-02 14:26:32', '2026-09-13 03:46:31'),
(14, 'ephraimbetoy2@gmail.com', '14_20260928134703.png', NULL, NULL, '2026-09-06 23:14:12', NULL, NULL, 0, '2026-09-06 23:13:40', '$2y$12$6zvuK9FmlDt59PkJMHEmresG/Ro8uLoKAlUE8rx51zfabbVK5jDQq', 'CEO', 'CEO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'requestor', 1, 'outsider', NULL, NULL, 'CEO COMPANY', '09537744571', 'Xbyzf5UotkDeCBP5NDxM0rZR6pORNRNsv87gUeqyw8eLrJb2TY5ByKGSKjNg', '2026-09-06 23:13:40', '2026-09-28 20:47:50'),
(15, 'kilgyro@gmail.com', NULL, NULL, NULL, NULL, '$2y$12$Ob7/dKo9aDP.iL9Qx3ohCepo.sDaIjZlIZH2u7eXRyENz5KvVIMAm', '2026-09-08 17:15:42', 0, '2026-09-08 17:05:42', '$2y$12$.UVM0jF8mBJmoXC4o5ZYze1Q5MPtsq4shl7NOUZogrwG8mO/bmtpy', 'daniel', 'daniel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'requestor', 1, 'outsider', NULL, NULL, 'CHOOKS TO GO', '09535472869', NULL, '2026-09-06 23:17:10', '2026-09-08 17:09:55'),
(16, 'kidmundane@gmail.com', '16_20260927140340.png', NULL, NULL, '2026-09-06 23:23:23', NULL, NULL, 0, '2026-09-06 23:22:27', '$2y$12$KHgpKcM4z0ZA6PHGouY8PuYpWBUdyMvZWGAjvpQuq2OG2dFlivV3O', 'prime', 'prime', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'student', 1, 'outsider', NULL, NULL, NULL, NULL, NULL, '2026-09-06 23:22:26', '2026-09-27 21:03:40'),
(17, 'grovestreetfam@gmail.com', NULL, NULL, NULL, '2026-09-24 04:55:01', NULL, NULL, 0, NULL, '$2y$12$GaxwhHJ1mtEVPOZEjXsHCeACFlVBeADYythZ9jbrUmH4D8jMDaPx.', 'Carl Nigger Johnson', 'Johnson', 'Carl', 'Nigger', NULL, 'Doctoral Programs', 10, 39, 'Adviser', 'requestor', 1, 'faculty', NULL, '12-1235-56', NULL, '099999856452', NULL, '2026-09-24 04:55:01', '2026-09-24 04:55:01'),
(18, 'krukawa1103@gmail.com', '18_20260928224038.png', NULL, NULL, '2026-09-29 02:10:06', NULL, NULL, 0, NULL, '$2y$12$gPwFaImM4UGkrAAQsMN6AeKQEZz0.voZRLM5vGcr3YgT1ajH.nOFO', 'Example Test Account', 'Account', 'Example', 'Test', NULL, 'Elementary Education', 7, 26, 'President', 'requestor', 1, 'student', '26-1234-567', NULL, NULL, '09553494424', 'DgxclxHwexWUhU8hwDj0HcrMyignKKfhHRhoHb1jwiRS7p0G3GRHlem2PTe2', '2026-09-29 02:10:06', '2026-09-29 05:40:38');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `custodian_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `name`, `capacity`, `is_active`, `custodian_id`, `created_at`, `updated_at`) VALUES
(1, 'Conference Hall & Interaction Center (CHIC)', 300, 1, 6, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(2, 'Gymnasium', 1000, 1, 7, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(3, 'Balay Alumni', 50, 1, 5, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(4, 'Oval Grounds', NULL, 1, 7, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(5, 'Covered Court', 200, 1, 7, '2026-09-01 11:03:44', '2026-09-01 11:03:44'),
(6, 'Volleyball Court', 200, 1, 7, '2026-09-01 11:03:44', '2026-09-01 11:03:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `colleges_name_unique` (`name`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_college_id_name_unique` (`college_id`,`name`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_schedules_venue_id_foreign` (`venue_id`),
  ADD KEY `maintenance_schedules_equipment_id_foreign` (`equipment_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `request_change_requests`
--
ALTER TABLE `request_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_change_requests_facility_request_id_status_index` (`facility_request_id`,`status`),
  ADD KEY `request_change_requests_requested_by_id_foreign` (`requested_by_id`),
  ADD KEY `request_change_requests_decided_by_id_foreign` (`decided_by_id`);

--
-- Indexes for table `request_equipment`
--
ALTER TABLE `request_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_equipment_facility_request_id_foreign` (`facility_request_id`),
  ADD KEY `request_equipment_equipment_id_foreign` (`equipment_id`);

--
-- Indexes for table `request_histories`
--
ALTER TABLE `request_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_histories_facility_request_id_foreign` (`facility_request_id`),
  ADD KEY `request_histories_user_id_foreign` (`user_id`);

--
-- Indexes for table `request_status_history`
--
ALTER TABLE `request_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_status_history_facility_request_id_foreign` (`facility_request_id`);

--
-- Indexes for table `request_venues`
--
ALTER TABLE `request_venues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_venues_facility_request_id_foreign` (`facility_request_id`),
  ADD KEY `request_venues_venue_id_foreign` (`venue_id`);

--
-- Indexes for table `reservation_reminder_logs`
--
ALTER TABLE `reservation_reminder_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservation_reminder_logs_unique_reminder` (`facility_request_id`,`reminder_type`,`scheduled_for`);

--
-- Indexes for table `reservation_schedules`
--
ALTER TABLE `reservation_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_schedules_facility_request_id_index` (`facility_request_id`),
  ADD KEY `reservation_schedules_start_datetime_index` (`start_datetime`),
  ADD KEY `reservation_schedules_end_datetime_index` (`end_datetime`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `student_organizations`
--
ALTER TABLE `student_organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_organizations_name_unique` (`name`),
  ADD KEY `student_organizations_college_id_foreign` (`college_id`),
  ADD KEY `student_organizations_department_id_foreign` (`department_id`);

--
-- Indexes for table `student_organization_members`
--
ALTER TABLE `student_organization_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `org_member_user_org_unique` (`user_id`,`student_organization_id`),
  ADD KEY `student_organization_members_student_organization_id_foreign` (`student_organization_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `system_settings_key_unique` (`key`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venues_custodian_id_foreign` (`custodian_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility_requests`
--
ALTER TABLE `facility_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_change_requests`
--
ALTER TABLE `request_change_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_equipment`
--
ALTER TABLE `request_equipment`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `request_histories`
--
ALTER TABLE `request_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `request_status_history`
--
ALTER TABLE `request_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_venues`
--
ALTER TABLE `request_venues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `reservation_reminder_logs`
--
ALTER TABLE `reservation_reminder_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_schedules`
--
ALTER TABLE `reservation_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `revision_histories`
--
ALTER TABLE `revision_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_organizations`
--
ALTER TABLE `student_organizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `student_organization_members`
--
ALTER TABLE `student_organization_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_college_id_foreign` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD CONSTRAINT `maintenance_schedules_equipment_id_foreign` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `maintenance_schedules_venue_id_foreign` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_change_requests`
--
ALTER TABLE `request_change_requests`
  ADD CONSTRAINT `request_change_requests_decided_by_id_foreign` FOREIGN KEY (`decided_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `request_change_requests_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_change_requests_requested_by_id_foreign` FOREIGN KEY (`requested_by_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `request_equipment`
--
ALTER TABLE `request_equipment`
  ADD CONSTRAINT `request_equipment_equipment_id_foreign` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `request_equipment_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_histories`
--
ALTER TABLE `request_histories`
  ADD CONSTRAINT `request_histories_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_status_history`
--
ALTER TABLE `request_status_history`
  ADD CONSTRAINT `request_status_history_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_venues`
--
ALTER TABLE `request_venues`
  ADD CONSTRAINT `request_venues_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_venues_venue_id_foreign` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reservation_reminder_logs`
--
ALTER TABLE `reservation_reminder_logs`
  ADD CONSTRAINT `reservation_reminder_logs_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_schedules`
--
ALTER TABLE `reservation_schedules`
  ADD CONSTRAINT `reservation_schedules_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_organizations`
--
ALTER TABLE `student_organizations`
  ADD CONSTRAINT `student_organizations_college_id_foreign` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_organizations_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_organization_members`
--
ALTER TABLE `student_organization_members`
  ADD CONSTRAINT `student_organization_members_student_organization_id_foreign` FOREIGN KEY (`student_organization_id`) REFERENCES `student_organizations` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `student_organization_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `venues`
--
ALTER TABLE `venues`
  ADD CONSTRAINT `venues_custodian_id_foreign` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

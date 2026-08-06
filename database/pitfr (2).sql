-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 03, 2026 at 07:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pitfr`
--

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `quantity_available` int(11) NOT NULL DEFAULT 1,
  `custodian_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `quantity`, `quantity_available`, `custodian_id`, `created_at`, `updated_at`) VALUES
(1, 'Sound System', 2, 2, 6, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(2, 'Microphones', 10, 10, 7, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(3, 'Canopies', 5, 5, 7, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(4, 'Industrial Fans', 8, 8, 8, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(5, 'Iwata Cooler Fans', 4, 4, 9, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(6, 'Tables', 30, 30, 7, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(7, 'Monobloc chairs', 100, 100, 7, '2026-05-02 23:23:09', '2026-05-02 23:23:09');

-- --------------------------------------------------------

--
-- Table structure for table `facility_requests`
--

CREATE TABLE `facility_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `control_number` varchar(30) NOT NULL,
  `date_requested` date NOT NULL,
  `department` varchar(100) NOT NULL,
  `name_of_activity` varchar(200) NOT NULL,
  `expected_participants` varchar(20) NOT NULL,
  `requesting_date` date NOT NULL,
  `requesting_end_date` date DEFAULT NULL,
  `time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `venue` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`venue`)),
  `equipment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`equipment`)),
  `equipment_quantities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment_quantities`)),
  `other_venue` varchar(200) DEFAULT NULL,
  `other_equipment` varchar(200) DEFAULT NULL,
  `requested_by` varchar(100) NOT NULL,
  `requested_by_position` varchar(100) NOT NULL,
  `requested_by_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `venue_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `equipment_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `equipment_custodian_statuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment_custodian_statuses`)),
  `venue_approved_by` varchar(100) DEFAULT NULL,
  `equipment_approved_by` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `venue_notes` text DEFAULT NULL,
  `equipment_notes` text DEFAULT NULL,
  `proposal_file` varchar(255) DEFAULT NULL,
  `equipment_returned_status` enum('pending','partial','returned','overdue') NOT NULL DEFAULT 'pending',
  `equipment_returned_by` varchar(100) DEFAULT NULL,
  `equipment_returned_date` datetime DEFAULT NULL,
  `equipment_return_notes` text DEFAULT NULL,
  `equipment_returned_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment_returned_items`)),
  `priority` enum('regular','institutional') NOT NULL DEFAULT 'regular',
  `is_emergency` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facility_requests`
--

INSERT INTO `facility_requests` (`id`, `control_number`, `date_requested`, `department`, `name_of_activity`, `expected_participants`, `requesting_date`, `requesting_end_date`, `time`, `end_time`, `venue`, `equipment`, `equipment_quantities`, `other_venue`, `other_equipment`, `requested_by`, `requested_by_position`, `requested_by_id`, `status`, `venue_status`, `equipment_status`, `equipment_custodian_statuses`, `venue_approved_by`, `equipment_approved_by`, `approved_by`, `approved_date`, `notes`, `venue_notes`, `equipment_notes`, `proposal_file`, `equipment_returned_status`, `equipment_returned_by`, `equipment_returned_date`, `equipment_return_notes`, `equipment_returned_items`, `priority`, `is_emergency`, `created_at`, `updated_at`) VALUES
(2, 'FER-2026-1145', '2026-05-02', 'BONDED INFORMATION TECHNOLOGY STUDENTS - BITS', 'CONVERGE 2026', '250', '2026-05-23', '2026-05-24', '19:00:00', NULL, '[\"Gymnasium\"]', '[\"Sound System\",\"Microphones\",\"Industrial Fans\"]', NULL, NULL, NULL, 'BITS ORG', 'PRESIDENT', 1, 'approved', 'approved', 'approved', '{\"6\":\"approved\",\"7\":\"approved\",\"8\":\"approved\"}', 'CHARLES ROMMEL L. TADO', 'L. ALMERINO', 'Supply Office', '2026-05-03 07:15:07', NULL, '', '', NULL, 'pending', NULL, NULL, NULL, NULL, 'institutional', 0, '2026-05-02 01:19:31', '2026-05-02 23:15:07'),
(3, 'FER-2026-6172', '2026-05-03', 'BONDED INFORMATION TECHNOLOGY STUDENTS', 'CONVERGE 2026', '250', '2026-05-21', '2026-05-22', '19:00:00', '00:00:00', '[\"Gymnasium\"]', '[\"Sound System\",\"Microphones\",\"Industrial Fans\"]', NULL, NULL, NULL, 'BITS ORG', 'PRESIDENT', 1, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, 'institutional', 0, '2026-05-03 04:00:06', '2026-05-03 04:00:06'),
(4, 'FER-2026-2966', '2026-05-03', 'asdasdasd', 'asdadasdad', '100', '2026-05-05', '2026-05-06', '19:00:00', '00:00:00', '[\"Conference Hall & Interaction Center (CHIC)\",\"Gymnasium\",\"Balay Alumni\",\"Oval Grounds\",\"Covered Court\",\"Volleyball Court\"]', '[\"Sound System\",\"Microphones\",\"Canopies\",\"Industrial Fans\",\"Iwata Cooler Fans\",\"Tables\",\"Monobloc chairs\"]', NULL, NULL, NULL, 'BITS ORG', 'asdasdasd', 1, 'pending', 'rejected', 'pending', NULL, 'ARLENE L. SALA', NULL, NULL, NULL, NULL, 'Too many venues. Please choose 1', NULL, NULL, 'pending', NULL, NULL, NULL, NULL, 'institutional', 0, '2026-05-03 04:10:27', '2026-05-03 04:34:43'),
(5, 'FER-2026-9315', '2026-05-03', 'palku', 'kopal yarn', '100', '2026-05-12', '2026-05-13', '19:00:00', '00:00:00', '[\"Conference Hall & Interaction Center (CHIC)\"]', '[\"Sound System\",\"Microphones\",\"Canopies\",\"Industrial Fans\",\"Iwata Cooler Fans\",\"Tables\",\"Monobloc chairs\"]', NULL, NULL, NULL, 'BITS ORG', 'asdasdasdas', 1, 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, 'institutional', 0, '2026-05-03 04:19:07', '2026-05-03 04:19:07');

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
(8, 'default', '{\"uuid\":\"ef260f54-3818-4f1b-83c3-9b67d0d95591\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":3:{s:9:\\\"requestId\\\";i:2;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1145\\\";s:8:\\\"userName\\\";s:8:\\\"BITS ORG\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713571,\"delay\":null}', 0, NULL, 1777713571, 1777713571),
(9, 'default', '{\"uuid\":\"700b5cde-6027-4501-9429-0a38a603d253\",\"displayName\":\"App\\\\Events\\\\RequestApproved\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:26:\\\"App\\\\Events\\\\RequestApproved\\\":4:{s:9:\\\"requestId\\\";i:2;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1145\\\";s:12:\\\"approvalType\\\";s:5:\\\"venue\\\";s:8:\\\"userName\\\";s:22:\\\"CHARLES ROMMEL L. TADO\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713624,\"delay\":null}', 0, NULL, 1777713624, 1777713624),
(10, 'default', '{\"uuid\":\"067c45b9-2011-4379-a51d-91bb297bfb6d\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":4:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:14:\\\"venue_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:2:\\\"id\\\";s:36:\\\"7f03cc8b-4e41-49d5-8ec3-240df506a107\\\";}s:4:\\\"data\\\";a:6:{s:10:\\\"request_id\\\";i:2;s:14:\\\"control_number\\\";s:13:\\\"FER-2026-1145\\\";s:8:\\\"activity\\\";s:13:\\\"CONVERGE 2026\\\";s:6:\\\"status\\\";s:14:\\\"venue_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:10:\\\"updated_at\\\";s:19:\\\"2026-05-02 09:20:24\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713624,\"delay\":null}', 0, NULL, 1777713624, 1777713624),
(11, 'default', '{\"uuid\":\"1a9cc4a1-f058-4058-b5d3-f00a4a8dee6f\",\"displayName\":\"App\\\\Events\\\\RequestApproved\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:26:\\\"App\\\\Events\\\\RequestApproved\\\":4:{s:9:\\\"requestId\\\";i:2;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1145\\\";s:12:\\\"approvalType\\\";s:9:\\\"equipment\\\";s:8:\\\"userName\\\";s:17:\\\"ROGELIO GUILLEMER\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713641,\"delay\":null}', 0, NULL, 1777713641, 1777713641),
(12, 'default', '{\"uuid\":\"3310f016-460c-49d3-a30a-ffa75c4165fc\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":4:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:18:\\\"equipment_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:2:\\\"id\\\";s:36:\\\"16c8f568-3752-49c3-a5d2-3e215b5af1d9\\\";}s:4:\\\"data\\\";a:6:{s:10:\\\"request_id\\\";i:2;s:14:\\\"control_number\\\";s:13:\\\"FER-2026-1145\\\";s:8:\\\"activity\\\";s:13:\\\"CONVERGE 2026\\\";s:6:\\\"status\\\";s:18:\\\"equipment_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:10:\\\"updated_at\\\";s:19:\\\"2026-05-02 09:20:41\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713641,\"delay\":null}', 0, NULL, 1777713641, 1777713641),
(13, 'default', '{\"uuid\":\"18ed187f-67ab-464e-9d7b-38cf565d1516\",\"displayName\":\"App\\\\Events\\\\RequestApproved\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:26:\\\"App\\\\Events\\\\RequestApproved\\\":4:{s:9:\\\"requestId\\\";i:2;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1145\\\";s:12:\\\"approvalType\\\";s:9:\\\"equipment\\\";s:8:\\\"userName\\\";s:13:\\\"JAIME SURALTA\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713713,\"delay\":null}', 0, NULL, 1777713713, 1777713713),
(14, 'default', '{\"uuid\":\"58fa485d-14f4-4186-b3a3-918dae0331f8\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":4:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:18:\\\"equipment_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:2:\\\"id\\\";s:36:\\\"adddb393-783e-4810-a5a7-5dce28f15def\\\";}s:4:\\\"data\\\";a:6:{s:10:\\\"request_id\\\";i:2;s:14:\\\"control_number\\\";s:13:\\\"FER-2026-1145\\\";s:8:\\\"activity\\\";s:13:\\\"CONVERGE 2026\\\";s:6:\\\"status\\\";s:18:\\\"equipment_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:10:\\\"updated_at\\\";s:19:\\\"2026-05-02 09:21:53\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713713,\"delay\":null}', 0, NULL, 1777713713, 1777713713),
(15, 'default', '{\"uuid\":\"d103d29b-13d6-437e-9241-d6696ddfaa30\",\"displayName\":\"App\\\\Events\\\\RequestApproved\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:26:\\\"App\\\\Events\\\\RequestApproved\\\":4:{s:9:\\\"requestId\\\";i:2;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-1145\\\";s:12:\\\"approvalType\\\";s:9:\\\"equipment\\\";s:8:\\\"userName\\\";s:11:\\\"L. ALMERINO\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713783,\"delay\":null}', 0, NULL, 1777713783, 1777713783),
(16, 'default', '{\"uuid\":\"c92eed10-0b39-4931-bee6-da5c83f09318\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":4:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:18:\\\"equipment_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:2:\\\"id\\\";s:36:\\\"96115616-e880-49b1-982e-594f84ca3511\\\";}s:4:\\\"data\\\";a:6:{s:10:\\\"request_id\\\";i:2;s:14:\\\"control_number\\\";s:13:\\\"FER-2026-1145\\\";s:8:\\\"activity\\\";s:13:\\\"CONVERGE 2026\\\";s:6:\\\"status\\\";s:18:\\\"equipment_approved\\\";s:5:\\\"notes\\\";s:0:\\\"\\\";s:10:\\\"updated_at\\\";s:19:\\\"2026-05-02 09:23:03\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777713783,\"delay\":null}', 0, NULL, 1777713783, 1777713783),
(17, 'default', '{\"uuid\":\"0cd2d69b-fd2f-4978-a336-3bf154b57446\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":3:{s:9:\\\"requestId\\\";i:3;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-6172\\\";s:8:\\\"userName\\\";s:8:\\\"BITS ORG\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777809607,\"delay\":null}', 0, NULL, 1777809607, 1777809607),
(18, 'default', '{\"uuid\":\"11f48f2f-7960-4ba6-a7ea-6332d619b475\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":3:{s:9:\\\"requestId\\\";i:4;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-2966\\\";s:8:\\\"userName\\\";s:8:\\\"BITS ORG\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777810227,\"delay\":null}', 0, NULL, 1777810227, 1777810227),
(19, 'default', '{\"uuid\":\"ba3e7b26-3054-4bdf-8aee-8dd07e470dcd\",\"displayName\":\"App\\\\Events\\\\RequestCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:25:\\\"App\\\\Events\\\\RequestCreated\\\":3:{s:9:\\\"requestId\\\";i:5;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-9315\\\";s:8:\\\"userName\\\";s:8:\\\"BITS ORG\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777810747,\"delay\":null}', 0, NULL, 1777810747, 1777810747),
(20, 'default', '{\"uuid\":\"0d98b22c-5818-43d4-9b2b-7f07142c30b4\",\"displayName\":\"App\\\\Events\\\\RequestApproved\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:26:\\\"App\\\\Events\\\\RequestApproved\\\":4:{s:9:\\\"requestId\\\";i:4;s:13:\\\"controlNumber\\\";s:13:\\\"FER-2026-2966\\\";s:12:\\\"approvalType\\\";s:5:\\\"venue\\\";s:8:\\\"userName\\\";s:14:\\\"ARLENE L. SALA\\\";}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777811683,\"delay\":null}', 0, NULL, 1777811683, 1777811683),
(21, 'default', '{\"uuid\":\"696bef9a-6e7a-4b2d-a392-257afd785982\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":17:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:38:\\\"App\\\\Notifications\\\\RequestStatusChanged\\\":4:{s:15:\\\"facilityRequest\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:26:\\\"App\\\\Models\\\\FacilityRequest\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:6:\\\"status\\\";s:14:\\\"venue_rejected\\\";s:5:\\\"notes\\\";s:32:\\\"Too many venues. Please choose 1\\\";s:2:\\\"id\\\";s:36:\\\"66803f38-6fbf-4db6-b4d8-46e45e964ef1\\\";}s:4:\\\"data\\\";a:6:{s:10:\\\"request_id\\\";i:4;s:14:\\\"control_number\\\";s:13:\\\"FER-2026-2966\\\";s:8:\\\"activity\\\";s:10:\\\"asdadasdad\\\";s:6:\\\"status\\\";s:14:\\\"venue_rejected\\\";s:5:\\\"notes\\\";s:32:\\\"Too many venues. Please choose 1\\\";s:10:\\\"updated_at\\\";s:19:\\\"2026-05-03 12:34:43\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";b:1;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1777811683,\"delay\":null}', 0, NULL, 1777811683, 1777811683);

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
(18, '2026_05_02_091351_create_personal_access_tokens_table', 2),
(19, '2026_05_02_120000_add_supply_office_role_to_users_enum', 3),
(20, '2026_05_03_152011_add_proposal_file_to_facility_requests_table', 4);

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
('0012c185-f132-4de7-aa36-439576018f77', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('16c8f568-3752-49c3-a5d2-3e215b5af1d9', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 1, '{\"request_id\":2,\"control_number\":\"FER-2026-1145\",\"activity\":\"CONVERGE 2026\",\"status\":\"equipment_approved\",\"notes\":\"\"}', NULL, '2026-05-02 01:20:41', '2026-05-02 01:20:41'),
('25df6613-2996-443e-abea-6b75b3c08135', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 4, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('2b3ec6c7-c63e-4b8a-894a-ae815e323153', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 4, '{\"request_id\":5,\"control_number\":\"FER-2026-9315\",\"activity\":\"kopal yarn\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:19:07', '2026-05-03 04:19:07'),
('3035a737-a38d-4fa3-aacd-3036db929bcd', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":5,\"control_number\":\"FER-2026-9315\",\"activity\":\"kopal yarn\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:19:07', '2026-05-03 04:19:07'),
('37f10df7-ad04-4e27-9816-e91c185361fd', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":5,\"control_number\":\"FER-2026-9315\",\"activity\":\"kopal yarn\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:19:07', '2026-05-03 04:19:07'),
('66803f38-6fbf-4db6-b4d8-46e45e964ef1', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 1, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"venue_rejected\",\"notes\":\"Too many venues. Please choose 1\"}', NULL, '2026-05-03 04:34:43', '2026-05-03 04:34:43'),
('78f6aaa8-bdad-4532-a04e-1eccdd72bc81', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('7df17c30-b2e9-4a2e-8bb0-c2c26d706f7e', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":5,\"control_number\":\"FER-2026-9315\",\"activity\":\"kopal yarn\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:19:07', '2026-05-03 04:19:07'),
('7f03cc8b-4e41-49d5-8ec3-240df506a107', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 1, '{\"request_id\":2,\"control_number\":\"FER-2026-1145\",\"activity\":\"CONVERGE 2026\",\"status\":\"venue_approved\",\"notes\":\"\"}', NULL, '2026-05-02 01:20:24', '2026-05-02 01:20:24'),
('7f503b89-a3b5-424d-a3a9-9f2fe977f909', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 3, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('96115616-e880-49b1-982e-594f84ca3511', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 1, '{\"request_id\":2,\"control_number\":\"FER-2026-1145\",\"activity\":\"CONVERGE 2026\",\"status\":\"equipment_approved\",\"notes\":\"\"}', NULL, '2026-05-02 01:23:03', '2026-05-02 01:23:03'),
('97d3f05d-b978-4a65-99e0-ee5843e06335', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 5, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('adddb393-783e-4810-a5a7-5dce28f15def', 'App\\Notifications\\RequestStatusChanged', 'App\\Models\\User', 1, '{\"request_id\":2,\"control_number\":\"FER-2026-1145\",\"activity\":\"CONVERGE 2026\",\"status\":\"equipment_approved\",\"notes\":\"\"}', NULL, '2026-05-02 01:21:53', '2026-05-02 01:21:53'),
('b9033cb3-55b9-4b5c-9c03-d3095abb9758', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 5, '{\"request_id\":3,\"control_number\":\"FER-2026-6172\",\"activity\":\"CONVERGE 2026\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:00:07', '2026-05-03 04:00:07'),
('bf3a4b8a-40ae-4cfe-bf95-d237cb5d389c', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 9, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('d2220ac1-3bb7-4618-ae8c-7490f5095a0a', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":4,\"control_number\":\"FER-2026-2966\",\"activity\":\"asdadasdad\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
('dce9322b-0852-48c1-998b-72ccafeafe62', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":3,\"control_number\":\"FER-2026-6172\",\"activity\":\"CONVERGE 2026\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:00:07', '2026-05-03 04:00:07'),
('dd6a7b93-272f-4164-ba29-85c74a2b42ae', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 8, '{\"request_id\":3,\"control_number\":\"FER-2026-6172\",\"activity\":\"CONVERGE 2026\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:00:07', '2026-05-03 04:00:07'),
('f0c623a9-b906-480c-9c60-de784baa7481', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 7, '{\"request_id\":5,\"control_number\":\"FER-2026-9315\",\"activity\":\"kopal yarn\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:19:07', '2026-05-03 04:19:07'),
('f5e014fb-c670-44f8-a3b9-7b0ba07f3a2e', 'App\\Notifications\\NewFacilityRequestNotification', 'App\\Models\\User', 6, '{\"request_id\":3,\"control_number\":\"FER-2026-6172\",\"activity\":\"CONVERGE 2026\",\"status\":\"new_request\",\"message\":\"A new facility request has been submitted and needs your review.\"}', NULL, '2026-05-03 04:00:07', '2026-05-03 04:00:07');

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
(6, 2, 1, 'submitted', 'Request submitted by BITS ORG', '2026-05-02 01:19:31', '2026-05-02 01:19:31', '2026-05-02 01:19:31'),
(7, 2, 5, 'venue_status_approved', 'Custodian CHARLES ROMMEL L. TADO approved the request', '2026-05-02 01:20:24', '2026-05-02 01:20:24', '2026-05-02 01:20:24'),
(8, 2, 6, 'equipment_status_approved', 'Custodian ROGELIO GUILLEMER approved the request', '2026-05-02 01:20:41', '2026-05-02 01:20:41', '2026-05-02 01:20:41'),
(9, 2, 7, 'equipment_status_approved', 'Custodian JAIME SURALTA approved the request', '2026-05-02 01:21:53', '2026-05-02 01:21:53', '2026-05-02 01:21:53'),
(10, 2, 8, 'equipment_status_approved', 'Custodian L. ALMERINO approved the request', '2026-05-02 01:23:03', '2026-05-02 01:23:03', '2026-05-02 01:23:03'),
(11, 2, 11, 'final_approved', 'Final approval granted by Supply Office', '2026-05-02 23:15:07', '2026-05-02 23:15:07', '2026-05-02 23:15:07'),
(12, 3, 1, 'submitted', 'Request submitted by BITS ORG', '2026-05-03 04:00:06', '2026-05-03 04:00:06', '2026-05-03 04:00:06'),
(13, 4, 1, 'submitted', 'Request submitted by BITS ORG', '2026-05-03 04:10:27', '2026-05-03 04:10:27', '2026-05-03 04:10:27'),
(14, 5, 1, 'submitted', 'Request submitted by BITS ORG', '2026-05-03 04:19:07', '2026-05-03 04:19:07', '2026-05-03 04:19:07'),
(15, 4, 4, 'venue_status_rejected', 'Custodian ARLENE L. SALA rejected the request', '2026-05-03 04:34:43', '2026-05-03 04:34:43', '2026-05-03 04:34:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` enum('student','faculty','custodian-venue','custodian-equipment','admin','supply_office') NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `department`, `position`, `role`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'student1', '$2y$12$MVu0nmvrkwH3lVdK99NPAuHQGpOH3R3k.WVimUcGXNP7g.YI8e7Hm', 'BITS ORG', NULL, NULL, 'student', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(2, 'faculty1', '$2y$12$Sm3zQbgD/9Yw0cLjStKadO5Wt6llAxgzkwk3C4.wwLgGrWqaR1PZG', 'IT Department', NULL, NULL, 'faculty', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(3, 'mmercado', '$2y$12$/Vj5o/fJON.xLx4VvuIkFOtCRvm6i7uhQmHou8M2w3det8kT8DN4W', 'MILDRED P. MERCADO', NULL, NULL, 'custodian-venue', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(4, 'asala', '$2y$12$MArIrBY0m6.U5dtsN3GP5eLq9TPV27r1RPG5.APOKJFDHBpGGbmNi', 'ARLENE L. SALA', NULL, NULL, 'custodian-venue', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(5, 'ctado', '$2y$12$tr/3/HGPb/4pltMPEVEO6eJzHbXdQ78qCCmLAWnUJ4206dsPIBCPK', 'CHARLES ROMMEL L. TADO', NULL, NULL, 'custodian-venue', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(6, 'rguillemer', '$2y$12$F/t73aH/YRQbxP./jfdoyOhREIDNSpmgZSLlH272t8m0JG7Uk4T56', 'ROGELIO GUILLEMER', NULL, NULL, 'custodian-equipment', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(7, 'jsuralta', '$2y$12$ocPDpR2KUHsi0iJ6UJvey.oIU750J1dRM0R/L3mKCMrVfJKxSZHKG', 'JAIME SURALTA', NULL, NULL, 'custodian-equipment', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(8, 'lalmerino', '$2y$12$.lhzcCInr7x/8Cyj9HD0teiaVSkBbLxpVVuXhe6RgdtPLh6Y5bseq', 'L. ALMERINO', NULL, NULL, 'custodian-equipment', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(9, 'jrvillas', '$2y$12$bgD0JXDaH984HsdqwcuSn./tOJQQGro7M.PzdXYnUjLWI1SfvQbqq', 'JR. VILLAS', NULL, NULL, 'custodian-equipment', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(10, 'admin', '$2y$12$ND2FuSCXXCBjwqdgzN78R.jsKvhCeVRT2ySE3cdXcOOaCE8fWYrn2', 'Administrator', NULL, NULL, 'admin', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(11, 'supplyoffice', '$2y$12$VH7TBgTL5ZMnp65W.grXHOcx.p3zePFzuLWNuElnfzRGMaMRkXAGS', 'Supply Office', NULL, NULL, 'supply_office', NULL, '2026-05-02 23:23:09', '2026-05-02 23:23:09');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `custodian_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `name`, `capacity`, `custodian_id`, `created_at`, `updated_at`) VALUES
(1, 'Conference Hall & Interaction Center (CHIC)', NULL, 4, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(2, 'Gymnasium', NULL, 5, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(3, 'Balay Alumni', NULL, 3, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(4, 'Oval Grounds', NULL, 5, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(5, 'Covered Court', NULL, 5, '2026-05-02 23:23:09', '2026-05-02 23:23:09'),
(6, 'Volleyball Court', NULL, 5, '2026-05-02 23:23:09', '2026-05-02 23:23:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_custodian_id_foreign` (`custodian_id`);

--
-- Indexes for table `facility_requests`
--
ALTER TABLE `facility_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `facility_requests_control_number_unique` (`control_number`),
  ADD KEY `facility_requests_requested_by_id_foreign` (`requested_by_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

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
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `request_histories`
--
ALTER TABLE `request_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_histories_facility_request_id_foreign` (`facility_request_id`),
  ADD KEY `request_histories_user_id_foreign` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_username_unique` (`username`);

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
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `facility_requests`
--
ALTER TABLE `facility_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_histories`
--
ALTER TABLE `request_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_custodian_id_foreign` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `facility_requests`
--
ALTER TABLE `facility_requests`
  ADD CONSTRAINT `facility_requests_requested_by_id_foreign` FOREIGN KEY (`requested_by_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `request_histories`
--
ALTER TABLE `request_histories`
  ADD CONSTRAINT `request_histories_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `venues`
--
ALTER TABLE `venues`
  ADD CONSTRAINT `venues_custodian_id_foreign` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

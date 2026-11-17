-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2026 at 10:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `buitems_complaints`
--

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `ticket_code` varchar(30) NOT NULL,
  `student_id` int(11) NOT NULL,
  `campus` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `department` varchar(100) NOT NULL,
  `location` varchar(150) NOT NULL,
  `incident_time` datetime NOT NULL,
  `description` text NOT NULL,
  `photo1` varchar(255) NOT NULL,
  `photo2` varchar(255) DEFAULT NULL,
  `photo3` varchar(255) DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Assigned','In Progress','Resolved','Escalated','Closed','Reopened') DEFAULT 'In Progress',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_staff_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `ticket_code`, `student_id`, `campus`, `title`, `department`, `location`, `incident_time`, `description`, `photo1`, `photo2`, `photo3`, `priority`, `status`, `admin_notes`, `created_at`, `assigned_staff_id`) VALUES
(1, 'CMP-2026-1001', 3, 'Takatu Campus (Airport Road)', 'Computers not working', 'Information Technology (IT)', 'A', '2026-11-14 07:45:00', 'We can\'t practice coding', 'uploads/CMP_1794638784_3_1.jpeg', NULL, NULL, 'High', 'Resolved', 'Your compliain is resolved', '2026-11-14 06:46:24', 6);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','staff','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Hooria Kamran', 'hooriakamran740@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2026-11-13 16:35:55'),
(2, 'Amna Kakar', '', '$2y$10$lqBRiw3gUoOJrj/Illi1Keb9SAtDuYPv8zRASs4oE1DRSDDKeyURW', 'student', '2026-11-13 17:39:49'),
(3, 'amna kakar', 'amnakakar1234@gmail.com', '$2y$10$2vm50NHEnlz5dSeLjUui3eDMLL2ZEIcO16oC32k/Ut0hZykqk2jUS', 'student', '2026-11-13 17:44:43'),
(4, 'hassan', 'hassanraheem123@gmail.com', '$2y$10$TzVrvljUPkuZOCKnv0B/lOzRDt5RSFW.QNJs3CSb/7MC6Lej/O3Sa', 'admin', '2026-11-13 19:31:10'),
(5, 'ali khan', 'alikhan432@gmail.com', '$2y$10$twieX6Jxm2gSVye4n0pA6./S0Onp03AmjZywqYoW68Cao1Z558lpu', 'admin', '2026-11-13 19:38:47'),
(6, 'ayesha khan', 'ayeshaali987@gmail.com', '$2y$10$RGENSZLEa2ZNAhRrotSCcux3.kqKI02FBOA/67zw/yTS3gGqz0RJ2', 'staff', '2026-11-13 19:41:44'),
(7, 'Aisha Rahman', 'staff@buitems.edu.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', '2026-11-13 19:45:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_code` (`ticket_code`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

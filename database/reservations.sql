-- SQL script to create the database and reservations table for the St. Helena Parish site.
-- Run this script in phpMyAdmin or the MySQL CLI provided with XAMPP.

CREATE DATABASE IF NOT EXISTS `st_helena_parish`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `st_helena_parish`;

CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `preferred_date` DATE NOT NULL,
  `preferred_time` VARCHAR(50) NOT NULL,
  `status` ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `notes` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


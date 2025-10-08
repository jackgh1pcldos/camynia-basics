-- ============================================================
-- CAMYNIA DATABASE SCHEMA
-- Multi-tenant Business Management Platform
-- MySQL 5.7+ / MariaDB 10.2+
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- CORE SYSTEM TABLES
-- ============================================================

-- Suppliers (Top-level entity - MSPs, parent companies)
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `phone` VARCHAR(50),
  `address` TEXT,
  `status` ENUM('active', 'suspended', 'cancelled') DEFAULT 'active',
  `seat_limit` INT UNSIGNED DEFAULT 50,
  `settings` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Companies (Tenants - businesses using the platform)
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT UNSIGNED NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(100) UNIQUE,
  `logo` VARCHAR(500),
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50),
  `address` TEXT,
  `website` VARCHAR(255),
  `timezone` VARCHAR(50) DEFAULT 'UTC',
  `status` ENUM('active', 'suspended', 'cancelled') DEFAULT 'active',
  `settings` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  INDEX `idx_supplier` (`supplier_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users (Staff, admins, suppliers)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `supplier_id` INT UNSIGNED NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255),
  `phone` VARCHAR(50),
  `avatar` VARCHAR(500),
  `is_supplier` BOOLEAN DEFAULT FALSE,
  `is_company_admin` BOOLEAN DEFAULT FALSE,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `last_login_at` TIMESTAMP NULL,
  `email_verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_email_per_company` (`email`, `company_id`),
  INDEX `idx_company` (`company_id`),
  INDEX `idx_supplier` (`supplier_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SSO Connections (OAuth tokens)
CREATE TABLE IF NOT EXISTS `sso_connections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `provider` ENUM('google', 'microsoft', 'discord') NOT NULL,
  `provider_user_id` VARCHAR(255) NOT NULL,
  `access_token` TEXT,
  `refresh_token` TEXT,
  `token_expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_provider_user` (`provider`, `provider_user_id`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(150),
  `description` TEXT,
  `is_system` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  INDEX `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `display_name` VARCHAR(150),
  `category` VARCHAR(50),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role-Permission pivot
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-Role pivot
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- HR MODULE TABLES
-- ============================================================

-- Departments
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `manager_id` INT UNSIGNED NULL,
  `time_off_allowances` JSON COMMENT 'Allowances per type: {"vacation": 20, "sick": 10, "personal": 5}',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employees (extends users with HR data)
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `employee_number` VARCHAR(50) UNIQUE,
  `department_id` INT UNSIGNED NULL,
  `job_title` VARCHAR(255),
  `employment_type` ENUM('full-time', 'part-time', 'contractor', 'intern') DEFAULT 'full-time',
  `hire_date` DATE,
  `termination_date` DATE NULL,
  `manager_id` INT UNSIGNED NULL,
  `salary` DECIMAL(12,2),
  `status` ENUM('active', 'on_leave', 'terminated') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_company` (`company_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time Off Requests
CREATE TABLE IF NOT EXISTS `time_off_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('vacation', 'sick', 'personal', 'unpaid', 'other') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `days_count` DECIMAL(4,1) NOT NULL,
  `reason` TEXT,
  `status` ENUM('pending', 'approved', 'denied', 'cancelled') DEFAULT 'pending',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_company_status` (`company_id`, `status`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RECRUITING MODULE TABLES
-- ============================================================

-- Job Postings
CREATE TABLE IF NOT EXISTS `job_postings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `department_id` INT UNSIGNED NULL,
  `employment_type` ENUM('full-time', 'part-time', 'contractor', 'intern') DEFAULT 'full-time',
  `location` VARCHAR(255),
  `salary_min` DECIMAL(12,2),
  `salary_max` DECIMAL(12,2),
  `description` TEXT,
  `requirements` TEXT,
  `status` ENUM('draft', 'open', 'closed', 'filled') DEFAULT 'draft',
  `posted_date` DATE,
  `closing_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  INDEX `idx_company_status` (`company_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job Applications
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_posting_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50),
  `resume_url` VARCHAR(500),
  `cover_letter` TEXT,
  `status` ENUM('new', 'screening', 'interview', 'offer', 'rejected', 'hired') DEFAULT 'new',
  `notes` TEXT,
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings`(`id`) ON DELETE CASCADE,
  INDEX `idx_job_status` (`job_posting_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PERFORMANCE MODULE TABLES
-- ============================================================

-- Performance Reviews
CREATE TABLE IF NOT EXISTS `performance_reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `reviewer_id` INT UNSIGNED NOT NULL,
  `review_period` VARCHAR(50),
  `review_date` DATE,
  `overall_rating` DECIMAL(3,2),
  `strengths` TEXT,
  `areas_for_improvement` TEXT,
  `goals` TEXT,
  `comments` TEXT,
  `status` ENUM('draft', 'submitted', 'completed') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_company_employee` (`company_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goals
CREATE TABLE IF NOT EXISTS `goals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `target_date` DATE,
  `progress` INT DEFAULT 0,
  `status` ENUM('not_started', 'in_progress', 'completed', 'cancelled') DEFAULT 'not_started',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_company_employee` (`company_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ATTENDANCE MODULE TABLES
-- ============================================================

-- Time Entries
CREATE TABLE IF NOT EXISTS `time_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `clock_in` DATETIME NOT NULL,
  `clock_out` DATETIME NULL,
  `break_minutes` INT DEFAULT 0,
  `notes` TEXT,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_company_user_date` (`company_id`, `user_id`, `clock_in`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ONBOARDING MODULE TABLES
-- ============================================================

-- Onboarding Checklists
CREATE TABLE IF NOT EXISTS `onboarding_checklists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `type` ENUM('onboarding', 'offboarding') DEFAULT 'onboarding',
  `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
  `start_date` DATE,
  `completion_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onboarding Tasks
CREATE TABLE IF NOT EXISTS `onboarding_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `checklist_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `assigned_to` INT UNSIGNED NULL,
  `due_date` DATE,
  `completed` BOOLEAN DEFAULT FALSE,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`checklist_id`) REFERENCES `onboarding_checklists`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRAINING MODULE TABLES
-- ============================================================

-- Training Courses
CREATE TABLE IF NOT EXISTS `training_courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `type` ENUM('mandatory', 'optional') DEFAULT 'optional',
  `duration_hours` DECIMAL(5,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Enrollments
CREATE TABLE IF NOT EXISTS `training_enrollments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `assigned_date` DATE,
  `due_date` DATE,
  `completion_date` DATE NULL,
  `status` ENUM('not_started', 'in_progress', 'completed', 'overdue') DEFAULT 'not_started',
  `score` DECIMAL(5,2) NULL,
  FOREIGN KEY (`course_id`) REFERENCES `training_courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_enrollment` (`course_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- KNOWLEDGE BASE MODULE
-- ============================================================

-- KB Categories (hierarchical tree structure)
CREATE TABLE IF NOT EXISTS `kb_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL COMMENT 'NULL for global supplier categories',
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(100) COMMENT 'Bootstrap icon class',
  `color` VARCHAR(20) COMMENT 'Hex color for badge',
  `sort_order` INT DEFAULT 0,
  `is_visible` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `kb_categories`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_company_slug` (`company_id`, `slug`),
  INDEX `idx_parent` (`parent_id`),
  INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Tags
CREATE TABLE IF NOT EXISTS `kb_tags` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_company_slug` (`company_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Collections (curated article groups)
CREATE TABLE IF NOT EXISTS `kb_collections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(100),
  `color` VARCHAR(20),
  `sort_order` INT DEFAULT 0,
  `is_visible` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_company_slug` (`company_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Articles
CREATE TABLE IF NOT EXISTS `kb_articles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL COMMENT 'NULL for global supplier articles',
  `category_id` INT UNSIGNED NULL,
  `author_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(500) NOT NULL,
  `slug` VARCHAR(500) NOT NULL,
  `summary` TEXT COMMENT 'Short excerpt for listings',
  `content_html` LONGTEXT NOT NULL,
  `content_markdown` LONGTEXT COMMENT 'Original markdown if available',
  `template_type` ENUM('how-to', 'faq', 'runbook', 'release-notes', 'general') DEFAULT 'general',

  -- Visibility & Access
  `visibility` ENUM('public', 'private_company', 'private_roles', 'global_supplier_only') DEFAULT 'private_company',
  `allowed_roles` JSON COMMENT 'Array of role IDs if private_roles',

  -- Status & Workflow
  `status` ENUM('draft', 'in_review', 'published', 'archived') DEFAULT 'draft',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL,

  -- Scheduling
  `publish_at` TIMESTAMP NULL,
  `unpublish_at` TIMESTAMP NULL,

  -- SEO & Meta
  `meta_title` VARCHAR(255),
  `meta_description` TEXT,
  `og_image` VARCHAR(500),
  `canonical_url` VARCHAR(500),
  `keywords` TEXT COMMENT 'Comma-separated for search',

  -- Review & Recertification
  `review_cycle_days` INT COMMENT 'How often article needs review',
  `last_reviewed_at` TIMESTAMP NULL,
  `next_review_at` TIMESTAMP NULL,

  -- Localization (future)
  `language` VARCHAR(10) DEFAULT 'en',

  -- Metrics
  `view_count` INT UNSIGNED DEFAULT 0,
  `helpful_count` INT UNSIGNED DEFAULT 0,
  `not_helpful_count` INT UNSIGNED DEFAULT 0,
  `reading_time_minutes` INT UNSIGNED DEFAULT 0 COMMENT 'Estimated reading time in minutes',

  -- Version tracking
  `version` INT UNSIGNED DEFAULT 1,

  -- Timestamps
  `published_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `kb_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,

  UNIQUE KEY `unique_company_slug` (`company_id`, `slug`),
  INDEX `idx_status` (`status`),
  INDEX `idx_visibility` (`visibility`),
  INDEX `idx_published` (`published_at`),
  FULLTEXT KEY `ft_search` (`title`, `summary`, `content_html`, `keywords`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Article Versions (immutable history)
CREATE TABLE IF NOT EXISTS `kb_article_versions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `title` VARCHAR(500) NOT NULL,
  `content_html` LONGTEXT NOT NULL,
  `content_markdown` LONGTEXT,
  `published_by` INT UNSIGNED NULL,
  `change_summary` TEXT COMMENT 'What changed in this version',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`published_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_article_version` (`article_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Article Tags (many-to-many)
CREATE TABLE IF NOT EXISTS `kb_article_tags` (
  `article_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`, `tag_id`),
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `kb_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Collection Articles (many-to-many)
CREATE TABLE IF NOT EXISTS `kb_collection_articles` (
  `collection_id` INT UNSIGNED NOT NULL,
  `article_id` INT UNSIGNED NOT NULL,
  `sort_order` INT DEFAULT 0,
  PRIMARY KEY (`collection_id`, `article_id`),
  FOREIGN KEY (`collection_id`) REFERENCES `kb_collections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Article Attachments
CREATE TABLE IF NOT EXISTS `kb_attachments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_type` VARCHAR(100),
  `file_size` INT UNSIGNED,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Article Feedback
CREATE TABLE IF NOT EXISTS `kb_article_feedback` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL COMMENT 'NULL for anonymous public feedback',
  `is_helpful` BOOLEAN NOT NULL,
  `comment` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_helpful` (`is_helpful`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Article Views (for analytics)
CREATE TABLE IF NOT EXISTS `kb_article_views` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `session_id` VARCHAR(100),
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `referrer` VARCHAR(500),
  `time_on_page` INT COMMENT 'Seconds spent on page',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_article_date` (`article_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Search Queries (track what users search for)
CREATE TABLE IF NOT EXISTS `kb_search_queries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `query` VARCHAR(500) NOT NULL,
  `results_count` INT UNSIGNED DEFAULT 0,
  `clicked_article_id` INT UNSIGNED NULL COMMENT 'Which article they clicked',
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`clicked_article_id`) REFERENCES `kb_articles`(`id`) ON DELETE SET NULL,
  INDEX `idx_zero_results` (`results_count`),
  INDEX `idx_query` (`query`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB Audit Log
CREATE TABLE IF NOT EXISTS `kb_audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `action` ENUM('created', 'updated', 'published', 'unpublished', 'archived', 'deleted', 'reviewed', 'rolled_back') NOT NULL,
  `changes` JSON COMMENT 'What fields changed',
  `version_number` INT UNSIGNED,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_article` (`article_id`),
  INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTENT MODERATION TABLES
-- ============================================================

-- Blacklisted Words (for content filtering)
CREATE TABLE IF NOT EXISTS `blacklisted_words` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `word` VARCHAR(255) NOT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium' COMMENT 'Severity level of the word',
  `category` VARCHAR(100) COMMENT 'Category (profanity, hate-speech, spam, etc)',
  `added_by` INT UNSIGNED NOT NULL,
  `removed_by` INT UNSIGNED NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `notes` TEXT COMMENT 'Optional notes about this word',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `removed_at` TIMESTAMP NULL,
  FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`removed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_active_word` (`word`, `is_active`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_severity` (`severity`),
  INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blacklist Audit Log
CREATE TABLE IF NOT EXISTS `blacklist_audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `blacklist_id` INT UNSIGNED NULL,
  `word` VARCHAR(255) NOT NULL,
  `action` ENUM('added', 'updated', 'removed', 'reactivated') NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `changes` JSON COMMENT 'What fields changed',
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`blacklist_id`) REFERENCES `blacklisted_words`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_word` (`word`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- HELPDESK / SERVICE DESK MODULE
-- ============================================================

-- Ticket Queues (First Line, Second Line, Back Office, etc.)
CREATE TABLE IF NOT EXISTS `ticket_queues` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL COMMENT 'NULL for global supplier queues',
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `email` VARCHAR(255) COMMENT 'Email address for this queue',
  `color` VARCHAR(20) COMMENT 'Hex color for badge',
  `icon` VARCHAR(100) COMMENT 'Bootstrap icon class',
  `sort_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `sla_first_response_hours` INT COMMENT 'SLA for first response in hours',
  `sla_resolution_hours` INT COMMENT 'SLA for resolution in hours',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_company_slug` (`company_id`, `slug`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Queue Staff Assignments (which staff can work on which queues)
CREATE TABLE IF NOT EXISTS `queue_staff` (
  `queue_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `can_assign` BOOLEAN DEFAULT TRUE COMMENT 'Can assign tickets to this queue',
  `can_view` BOOLEAN DEFAULT TRUE COMMENT 'Can view tickets in this queue',
  `can_respond` BOOLEAN DEFAULT TRUE COMMENT 'Can respond to tickets',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`queue_id`, `user_id`),
  FOREIGN KEY (`queue_id`) REFERENCES `ticket_queues`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Priorities
CREATE TABLE IF NOT EXISTS `ticket_priorities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `level` INT NOT NULL COMMENT '1=Low, 2=Medium, 3=High, 4=Urgent, 5=Critical',
  `color` VARCHAR(20),
  `is_default` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Human-readable ticket number',
  `company_id` INT UNSIGNED NULL COMMENT 'Company this ticket belongs to',
  `queue_id` INT UNSIGNED NULL,
  `priority_id` INT UNSIGNED NULL,

  -- Contact Information
  `contact_name` VARCHAR(255) NOT NULL,
  `contact_email` VARCHAR(255) NOT NULL,
  `contact_phone` VARCHAR(50),
  `user_id` INT UNSIGNED NULL COMMENT 'If customer has an account',

  -- Ticket Details
  `subject` VARCHAR(500) NOT NULL,
  `description` LONGTEXT NOT NULL,
  `status` ENUM('new', 'open', 'pending', 'on-hold', 'resolved', 'closed', 'cancelled') DEFAULT 'new',

  -- Assignment
  `assigned_to` INT UNSIGNED NULL COMMENT 'Staff member assigned',
  `assigned_at` TIMESTAMP NULL,

  -- Resolution
  `resolution_notes` LONGTEXT COMMENT 'How the ticket was resolved',
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` TIMESTAMP NULL,
  `closed_at` TIMESTAMP NULL,

  -- SLA Tracking
  `first_response_at` TIMESTAMP NULL COMMENT 'Time of first staff response',
  `first_response_sla_breached` BOOLEAN DEFAULT FALSE,
  `resolution_sla_breached` BOOLEAN DEFAULT FALSE,

  -- Metadata
  `source` ENUM('web', 'email', 'phone', 'chat', 'api') DEFAULT 'web',
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `referrer` VARCHAR(500),

  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_customer_reply_at` TIMESTAMP NULL,
  `last_staff_reply_at` TIMESTAMP NULL,

  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`queue_id`) REFERENCES `ticket_queues`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,

  INDEX `idx_ticket_number` (`ticket_number`),
  INDEX `idx_status` (`status`),
  INDEX `idx_queue` (`queue_id`),
  INDEX `idx_assigned` (`assigned_to`),
  INDEX `idx_contact_email` (`contact_email`),
  INDEX `idx_created` (`created_at`),
  FULLTEXT KEY `ft_search` (`subject`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Replies (both staff and customer responses)
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL COMMENT 'NULL if from non-logged-in customer',
  `author_name` VARCHAR(255) NOT NULL,
  `author_email` VARCHAR(255) NOT NULL,
  `is_staff` BOOLEAN DEFAULT FALSE,
  `is_private` BOOLEAN DEFAULT FALSE COMMENT 'Private staff notes',
  `message` LONGTEXT NOT NULL,
  `message_html` LONGTEXT COMMENT 'HTML version if formatted',
  `source` ENUM('web', 'email', 'api') DEFAULT 'web',
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `email_message_id` VARCHAR(255) COMMENT 'Email Message-ID for threading',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_private` (`is_private`),
  FULLTEXT KEY `ft_search` (`message`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Attachments
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `reply_id` INT UNSIGNED NULL COMMENT 'If attached to a specific reply',
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_type` VARCHAR(100),
  `file_size` INT UNSIGNED,
  `uploaded_by` INT UNSIGNED NULL,
  `is_public` BOOLEAN DEFAULT TRUE COMMENT 'Public vs staff-only',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reply_id`) REFERENCES `ticket_replies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Watchers (staff who want to be notified)
CREATE TABLE IF NOT EXISTS `ticket_watchers` (
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`, `user_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket CC (additional email addresses to notify)
CREATE TABLE IF NOT EXISTS `ticket_cc` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255),
  `added_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_ticket_email` (`ticket_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Tags
CREATE TABLE IF NOT EXISTS `ticket_tags` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_company_slug` (`company_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Tag Assignments
CREATE TABLE IF NOT EXISTS `ticket_tag_assignments` (
  `ticket_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`ticket_id`, `tag_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `ticket_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Templates / Canned Responses
CREATE TABLE IF NOT EXISTS `ticket_templates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `queue_id` INT UNSIGNED NULL COMMENT 'Optional: template specific to queue',
  `name` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) COMMENT 'Optional subject line',
  `content` LONGTEXT NOT NULL,
  `content_html` LONGTEXT COMMENT 'HTML version',
  `is_global` BOOLEAN DEFAULT FALSE COMMENT 'Available to all staff',
  `created_by` INT UNSIGNED NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `use_count` INT UNSIGNED DEFAULT 0 COMMENT 'Track usage',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`queue_id`) REFERENCES `ticket_queues`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_active` (`is_active`),
  INDEX `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved Filters / Views
CREATE TABLE IF NOT EXISTS `ticket_filters` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'Owner of this filter',
  `name` VARCHAR(255) NOT NULL,
  `is_shared` BOOLEAN DEFAULT FALSE COMMENT 'Share with other staff',
  `is_default` BOOLEAN DEFAULT FALSE COMMENT 'Load by default',
  `filter_data` JSON NOT NULL COMMENT 'Filter conditions',
  `sort_by` VARCHAR(100) DEFAULT 'created_at',
  `sort_direction` ENUM('asc', 'desc') DEFAULT 'desc',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_shared` (`is_shared`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Activity Log
CREATE TABLE IF NOT EXISTS `ticket_activity_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL COMMENT 'created, assigned, status_changed, priority_changed, etc.',
  `field_name` VARCHAR(100) COMMENT 'Field that changed',
  `old_value` TEXT COMMENT 'Previous value',
  `new_value` TEXT COMMENT 'New value',
  `description` TEXT COMMENT 'Human-readable description',
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Tracking (for email piping integration)
CREATE TABLE IF NOT EXISTS `ticket_emails` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NULL,
  `message_id` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Email Message-ID',
  `in_reply_to` VARCHAR(255) COMMENT 'In-Reply-To header',
  `from_email` VARCHAR(255) NOT NULL,
  `to_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500),
  `body_text` LONGTEXT,
  `body_html` LONGTEXT,
  `headers` TEXT COMMENT 'Raw email headers',
  `processed` BOOLEAN DEFAULT FALSE,
  `created_reply_id` INT UNSIGNED NULL COMMENT 'Reply created from this email',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_reply_id`) REFERENCES `ticket_replies`(`id`) ON DELETE SET NULL,
  INDEX `idx_message_id` (`message_id`),
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket SLA Tracking
CREATE TABLE IF NOT EXISTS `ticket_sla_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `event_type` ENUM('first_response', 'resolution') NOT NULL,
  `sla_hours` INT NOT NULL COMMENT 'Expected SLA in hours',
  `actual_hours` DECIMAL(10,2) COMMENT 'Actual time taken',
  `breached` BOOLEAN DEFAULT FALSE,
  `breach_duration_hours` DECIMAL(10,2) COMMENT 'How long past SLA',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_breached` (`breached`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff Mentions in Tickets (tagging staff)
CREATE TABLE IF NOT EXISTS `ticket_mentions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `reply_id` INT UNSIGNED NULL,
  `mentioned_user_id` INT UNSIGNED NOT NULL,
  `mentioned_by` INT UNSIGNED NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reply_id`) REFERENCES `ticket_replies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`mentioned_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`mentioned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_mentioned` (`mentioned_user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

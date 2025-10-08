-- ============================================================
-- CAMYNIA DUMMY DATA
-- Sample data for testing and demonstration
-- Run this AFTER installing the database schema
-- Safe to run multiple times - will skip duplicates
-- ============================================================

-- Ensure missing columns exist (for older schema versions)

-- Add reading_time_minutes to kb_articles if missing
SET @dbname = DATABASE();
SET @tablename = 'kb_articles';
SET @columnname = 'reading_time_minutes';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `', @columnname, '` INT UNSIGNED DEFAULT 0 COMMENT ''Estimated reading time in minutes'' AFTER `not_helpful_count`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add user_agent to kb_article_views if missing
SET @tablename = 'kb_article_views';
SET @columnname = 'user_agent';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `', @columnname, '` VARCHAR(500) AFTER `ip_address`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insert Suppliers (skip if email exists)
INSERT IGNORE INTO suppliers (id, company_name, email, phone, address, status, seat_limit) VALUES
(1, 'EtherXI MSP', 'admin@etherxi.com', '+1-555-0100', '123 Tech Street, San Francisco, CA 94105', 'active', 100),
(2, 'Global IT Solutions', 'contact@globalitsolutions.com', '+1-555-0200', '456 Innovation Ave, Austin, TX 78701', 'active', 50);

-- Insert Companies (skip if slug exists)
INSERT IGNORE INTO companies (id, supplier_id, company_name, slug, email, phone, address, website, timezone, status) VALUES
(1, 1, 'Acme Corporation', 'acme-corp', 'info@acme-corp.com', '+1-555-1000', '789 Business Blvd, New York, NY 10001', 'https://acme-corp.com', 'America/New_York', 'active'),
(2, 1, 'TechStart Inc', 'techstart', 'hello@techstart.io', '+1-555-2000', '321 Startup Lane, San Jose, CA 95110', 'https://techstart.io', 'America/Los_Angeles', 'active'),
(3, 2, 'RetailPro LLC', 'retailpro', 'support@retailpro.com', '+1-555-3000', '555 Commerce Dr, Chicago, IL 60601', 'https://retailpro.com', 'America/Chicago', 'active');

-- Insert Departments for Company 1 (Acme Corporation)
INSERT IGNORE INTO departments (id, company_id, name, parent_id) VALUES
(1, 1, 'Engineering', NULL),
(2, 1, 'Sales', NULL),
(3, 1, 'Marketing', NULL),
(4, 1, 'Human Resources', NULL),
(5, 1, 'Finance', NULL),
(6, 1, 'Customer Support', NULL),
(7, 1, 'Backend Team', 1),
(8, 1, 'Frontend Team', 1);

-- Insert Users (Supplier Admins)
INSERT IGNORE INTO users (id, supplier_id, first_name, last_name, email, password_hash, is_supplier, status) VALUES
(1, 1, 'Jack', 'Jameson', 'jack@etherxi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active'),
(2, 2, 'Sarah', 'Mitchell', 'sarah@globalitsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active');

-- Insert Users (Company Staff) for Acme Corporation
INSERT IGNORE INTO users (id, company_id, first_name, last_name, email, password_hash, is_company_admin, status) VALUES
(3, 1, 'John', 'Smith', 'john.smith@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active'),
(4, 1, 'Emily', 'Johnson', 'emily.johnson@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(5, 1, 'Michael', 'Brown', 'michael.brown@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(6, 1, 'Sarah', 'Davis', 'sarah.davis@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(7, 1, 'David', 'Wilson', 'david.wilson@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(8, 1, 'Jennifer', 'Martinez', 'jennifer.martinez@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(9, 1, 'Robert', 'Taylor', 'robert.taylor@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(10, 1, 'Lisa', 'Anderson', 'lisa.anderson@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(11, 1, 'James', 'Thomas', 'james.thomas@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(12, 1, 'Mary', 'Jackson', 'mary.jackson@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(13, 1, 'William', 'White', 'william.white@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(14, 1, 'Patricia', 'Harris', 'patricia.harris@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(15, 1, 'Richard', 'Clark', 'richard.clark@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(16, 1, 'Linda', 'Lewis', 'linda.lewis@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active'),
(17, 1, 'Thomas', 'Robinson', 'thomas.robinson@acme-corp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active');

-- Insert Employees for Acme Corporation
INSERT IGNORE INTO employees (id, company_id, user_id, employee_number, department_id, job_title, employment_type, hire_date, manager_id, salary, status) VALUES
(1, 1, 3, 'EMP001', 1, 'VP of Engineering', 'full-time', '2020-01-15', NULL, 150000.00, 'active'),
(2, 1, 4, 'EMP002', 1, 'Senior Software Engineer', 'full-time', '2020-03-20', 3, 120000.00, 'active'),
(3, 1, 5, 'EMP003', 7, 'Backend Developer', 'full-time', '2021-05-10', 4, 95000.00, 'active'),
(4, 1, 6, 'EMP004', 8, 'Frontend Developer', 'full-time', '2021-06-15', 4, 90000.00, 'active'),
(5, 1, 7, 'EMP005', 2, 'Sales Director', 'full-time', '2019-11-01', NULL, 130000.00, 'active'),
(6, 1, 8, 'EMP006', 2, 'Account Executive', 'full-time', '2022-01-10', 7, 75000.00, 'active'),
(7, 1, 9, 'EMP007', 2, 'Sales Representative', 'full-time', '2022-08-15', 7, 60000.00, 'active'),
(8, 1, 10, 'EMP008', 3, 'Marketing Manager', 'full-time', '2021-02-01', NULL, 95000.00, 'active'),
(9, 1, 11, 'EMP009', 3, 'Content Specialist', 'full-time', '2022-04-20', 10, 65000.00, 'active'),
(10, 1, 12, 'EMP010', 4, 'HR Manager', 'full-time', '2020-07-01', NULL, 85000.00, 'active'),
(11, 1, 13, 'EMP011', 4, 'HR Coordinator', 'full-time', '2023-01-15', 12, 55000.00, 'active'),
(12, 1, 14, 'EMP012', 5, 'Finance Manager', 'full-time', '2019-09-01', NULL, 100000.00, 'active'),
(13, 1, 15, 'EMP013', 6, 'Support Lead', 'full-time', '2021-10-10', NULL, 70000.00, 'active'),
(14, 1, 16, 'EMP014', 6, 'Customer Support Rep', 'part-time', '2023-03-01', 15, 35000.00, 'active'),
(15, 1, 17, 'EMP015', 1, 'DevOps Engineer', 'contractor', '2023-06-01', 3, 110000.00, 'active');

-- Insert Time Off Requests
INSERT IGNORE INTO time_off_requests (id, company_id, user_id, type, start_date, end_date, days_count, reason, status, approved_by, approved_at) VALUES
(1, 1, 4, 'vacation', '2025-02-15', '2025-02-19', 5.0, 'Family vacation', 'approved', 3, '2025-01-10 10:30:00'),
(2, 1, 5, 'sick', '2025-01-20', '2025-01-20', 1.0, 'Doctor appointment', 'approved', 4, '2025-01-19 09:15:00'),
(3, 1, 8, 'vacation', '2025-03-01', '2025-03-07', 7.0, 'Spring break', 'pending', NULL, NULL),
(4, 1, 11, 'personal', '2025-01-25', '2025-01-25', 1.0, 'Personal matter', 'pending', NULL, NULL),
(5, 1, 6, 'vacation', '2025-04-10', '2025-04-14', 5.0, 'Wedding anniversary', 'approved', 7, '2025-01-15 14:20:00');

-- Insert Job Postings
INSERT IGNORE INTO job_postings (id, company_id, title, department_id, employment_type, location, salary_min, salary_max, description, requirements, status, posted_date, closing_date) VALUES
(1, 1, 'Senior Full Stack Developer', 1, 'full-time', 'Remote', 110000.00, 140000.00,
'We are seeking an experienced Full Stack Developer to join our growing engineering team. You will work on cutting-edge web applications using modern technologies.',
'5+ years experience with React, Node.js, and databases
Strong understanding of REST APIs and microservices
Experience with cloud platforms (AWS/Azure)
Excellent communication skills',
'open', '2025-01-01', '2025-03-01'),

(2, 1, 'Product Manager', 3, 'full-time', 'New York, NY (Hybrid)', 100000.00, 130000.00,
'Lead product strategy and roadmap for our core platform. Work cross-functionally with engineering, design, and business teams.',
'3+ years product management experience
MBA or equivalent experience
Strong analytical and communication skills
Experience with SaaS products',
'open', '2025-01-10', '2025-02-28'),

(3, 1, 'UX/UI Designer', 8, 'full-time', 'Remote', 80000.00, 105000.00,
'Create beautiful, user-friendly interfaces for our web and mobile applications.',
'Portfolio demonstrating UX/UI design skills
Proficiency in Figma or Sketch
Understanding of design systems
3+ years experience',
'open', '2025-01-05', '2025-02-15');

-- Insert Job Applications
INSERT IGNORE INTO job_applications (id, job_posting_id, first_name, last_name, email, phone, resume_url, cover_letter, status, notes) VALUES
(1, 1, 'Alex', 'Thompson', 'alex.thompson@email.com', '+1-555-4001', 'https://example.com/resumes/alex_thompson.pdf',
'I am excited to apply for the Senior Full Stack Developer position...',
'interview', 'Strong candidate, scheduled for technical interview'),

(2, 1, 'Jessica', 'Martinez', 'jessica.m@email.com', '+1-555-4002', 'https://example.com/resumes/jessica_martinez.pdf',
'With 7 years of experience in full stack development...',
'screening', 'Reviewing technical assessment'),

(3, 1, 'Brian', 'Lee', 'brian.lee@email.com', '+1-555-4003', 'https://example.com/resumes/brian_lee.pdf',
'I would love to bring my expertise to your team...',
'new', NULL),

(4, 2, 'Samantha', 'Green', 'samantha.green@email.com', '+1-555-4004', 'https://example.com/resumes/samantha_green.pdf',
'As a product manager with 5 years experience...',
'interview', 'Second round interview scheduled'),

(5, 3, 'Chris', 'Walker', 'chris.walker@email.com', '+1-555-4005', 'https://example.com/resumes/chris_walker.pdf',
'My passion for creating intuitive user experiences...',
'new', NULL);

-- Insert Performance Reviews
INSERT IGNORE INTO performance_reviews (id, company_id, employee_id, reviewer_id, review_period, review_date, overall_rating, strengths, areas_for_improvement, goals, comments, status) VALUES
(1, 1, 2, 1, 'Q4 2024', '2025-01-10', 4.5,
'Excellent technical skills and leadership
Mentors junior developers effectively
Consistently delivers high-quality code',
'Could improve documentation
Work on delegating tasks more effectively',
'Lead migration to new tech stack
Mentor 2 junior developers
Improve code review turnaround time',
'Great performance this quarter!',
'completed'),

(2, 1, 3, 2, 'Q4 2024', '2025-01-12', 4.0,
'Strong problem-solving abilities
Good team collaboration
Improved significantly over the year',
'Need to improve time estimation
Work on communication with stakeholders',
'Complete certification in cloud architecture
Contribute to open source project
Present at team tech talk',
'Keep up the good work!',
'completed');

-- Insert Goals
INSERT IGNORE INTO goals (id, company_id, employee_id, title, description, target_date, progress, status) VALUES
(1, 1, 2, 'Complete AWS Certification', 'Obtain AWS Solutions Architect certification', '2025-06-30', 60, 'in_progress'),
(2, 1, 2, 'Lead Tech Talk Series', 'Organize monthly tech talks for the engineering team', '2025-12-31', 25, 'in_progress'),
(3, 1, 3, 'Improve Code Coverage', 'Increase test coverage to 80% in backend services', '2025-03-31', 45, 'in_progress'),
(4, 1, 5, 'Complete Project Alpha', 'Successfully deliver Project Alpha on time', '2025-02-28', 75, 'in_progress'),
(5, 1, 6, 'Sales Target Q1', 'Achieve $500K in sales for Q1 2025', '2025-03-31', 30, 'in_progress');

-- Insert Time Entries
INSERT IGNORE INTO time_entries (id, company_id, user_id, clock_in, clock_out, break_minutes, status) VALUES
(1, 1, 4, '2025-01-06 09:00:00', '2025-01-06 17:30:00', 30, 'approved'),
(2, 1, 4, '2025-01-07 08:45:00', '2025-01-07 17:15:00', 45, 'approved'),
(3, 1, 5, '2025-01-06 09:15:00', '2025-01-06 18:00:00', 30, 'approved'),
(4, 1, 5, '2025-01-07 09:00:00', '2025-01-07 17:45:00', 30, 'approved'),
(5, 1, 8, '2025-01-06 08:30:00', '2025-01-06 17:00:00', 60, 'approved'),
(6, 1, 11, '2025-01-06 09:00:00', '2025-01-06 17:00:00', 30, 'pending');

-- Insert Onboarding Checklists
INSERT IGNORE INTO onboarding_checklists (id, company_id, employee_id, type, status, start_date, completion_date) VALUES
(1, 1, 15, 'onboarding', 'in_progress', '2023-06-01', NULL),
(2, 1, 14, 'onboarding', 'completed', '2023-03-01', '2023-03-15');

-- Insert Training Courses
INSERT IGNORE INTO training_courses (id, company_id, title, description, type, duration_hours) VALUES
(1, 1, 'Information Security Awareness', 'Annual mandatory security training covering data protection, phishing, and security best practices', 'mandatory', 2.0),
(2, 1, 'Diversity & Inclusion', 'Understanding and promoting diversity, equity, and inclusion in the workplace', 'mandatory', 1.5),
(3, 1, 'Leadership Fundamentals', 'Core leadership skills for managers and aspiring leaders', 'optional', 8.0),
(4, 1, 'Advanced Excel for Business', 'Master Excel formulas, pivot tables, and data analysis', 'optional', 6.0),
(5, 1, 'Effective Communication Skills', 'Improve written and verbal communication in professional settings', 'optional', 4.0);

-- Insert Training Enrollments
INSERT IGNORE INTO training_enrollments (id, course_id, user_id, assigned_date, due_date, completion_date, status, score) VALUES
(1, 1, 4, '2025-01-01', '2025-02-01', '2025-01-15', 'completed', 95.00),
(2, 1, 5, '2025-01-01', '2025-02-01', NULL, 'in_progress', NULL),
(3, 1, 6, '2025-01-01', '2025-02-01', NULL, 'not_started', NULL),
(4, 2, 4, '2025-01-01', '2025-02-15', '2025-01-10', 'completed', 100.00),
(5, 3, 3, '2024-12-01', '2025-03-01', NULL, 'in_progress', NULL),
(6, 4, 11, '2025-01-10', '2025-04-10', NULL, 'not_started', NULL);

-- ============================================================
-- KNOWLEDGE BASE DUMMY DATA
-- ============================================================

-- Insert KB Categories (Global supplier categories)
INSERT IGNORE INTO kb_categories (id, company_id, parent_id, name, slug, description, icon, color, sort_order, is_visible) VALUES
(1, NULL, NULL, 'Getting Started', 'getting-started', 'Essential guides to help you get started', 'rocket-takeoff', '#3498db', 1, 1),
(2, NULL, NULL, 'How-To Guides', 'how-to-guides', 'Step-by-step instructions for common tasks', 'list-check', '#2ecc71', 2, 1),
(3, NULL, NULL, 'Troubleshooting', 'troubleshooting', 'Solutions to common problems', 'tools', '#e74c3c', 3, 1),
(4, NULL, NULL, 'FAQ', 'faq', 'Frequently asked questions', 'question-circle', '#f39c12', 4, 1),
(5, NULL, NULL, 'Best Practices', 'best-practices', 'Recommended approaches and standards', 'award', '#9b59b6', 5, 1),
(6, NULL, NULL, 'API Documentation', 'api-docs', 'Technical API reference and guides', 'code-slash', '#34495e', 6, 1),
(7, NULL, 1, 'Quick Start', 'quick-start', 'Get up and running in minutes', 'lightning', '#3498db', 1, 1),
(8, NULL, 1, 'Initial Setup', 'initial-setup', 'Complete setup instructions', 'gear', '#3498db', 2, 1);

-- Insert KB Categories (Company-specific for Acme Corp)
INSERT IGNORE INTO kb_categories (id, company_id, parent_id, name, slug, description, icon, color, sort_order, is_visible) VALUES
(9, 1, NULL, 'Internal Policies', 'internal-policies', 'Company policies and procedures', 'file-text', '#16a085', 7, 1),
(10, 1, NULL, 'HR Resources', 'hr-resources', 'Human resources documentation', 'people', '#d35400', 8, 1);

-- Insert KB Tags
INSERT IGNORE INTO kb_tags (id, name, slug) VALUES
(1, 'authentication', 'authentication'),
(2, 'security', 'security'),
(3, 'api', 'api'),
(4, 'setup', 'setup'),
(5, 'configuration', 'configuration'),
(6, 'troubleshooting', 'troubleshooting'),
(7, 'beginner', 'beginner'),
(8, 'advanced', 'advanced'),
(9, 'integration', 'integration'),
(10, 'reporting', 'reporting'),
(11, 'automation', 'automation'),
(12, 'performance', 'performance');

-- Insert KB Articles (Global - Published)
INSERT IGNORE INTO kb_articles (id, company_id, category_id, author_id, title, slug, summary, content_html, template_type, visibility, status, version, view_count, helpful_count, not_helpful_count, reading_time_minutes, meta_title, meta_description, keywords, review_cycle_days, next_review_at, published_at, created_at, updated_at) VALUES
(1, NULL, 7, 1, 'Getting Started with Your Account', 'getting-started-with-your-account',
'Learn how to set up your account and start using the platform in just a few minutes.',
'<h2>Welcome!</h2>
<p>This guide will help you get started with your new account. Follow these simple steps to begin.</p>

<h2>Step 1: Verify Your Email</h2>
<p>Check your inbox for a verification email and click the confirmation link. This ensures your account is secure.</p>

<h2>Step 2: Complete Your Profile</h2>
<p>Add your basic information including:</p>
<ul>
<li>Your full name</li>
<li>Company details</li>
<li>Contact information</li>
<li>Timezone preferences</li>
</ul>

<h2>Step 3: Configure Settings</h2>
<p>Navigate to Settings and customize your experience. We recommend enabling two-factor authentication for enhanced security.</p>

<h2>Step 4: Invite Your Team</h2>
<p>Use the Team Management section to invite colleagues. You can assign roles and permissions as needed.</p>

<h2>Next Steps</h2>
<p>Once you''ve completed these steps, explore our other guides to learn more about advanced features.</p>',
'how-to', 'public', 'published', 1, 0, 0, 0, 5, 'Getting Started Guide - Account Setup', 'Quick start guide to set up your account and begin using the platform', 'getting started, setup, account, onboarding', 90, DATE_ADD(NOW(), INTERVAL 90 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),

(2, NULL, 2, 1, 'How to Reset Your Password', 'how-to-reset-your-password',
'Step-by-step instructions for resetting your password if you''ve forgotten it.',
'<h2>Resetting Your Password</h2>
<p>If you''ve forgotten your password, follow these steps to reset it:</p>

<h2>Method 1: From Login Page</h2>
<ol>
<li>Go to the login page</li>
<li>Click "Forgot Password?" below the login form</li>
<li>Enter your email address</li>
<li>Check your email for a password reset link</li>
<li>Click the link and enter your new password</li>
<li>Confirm your new password</li>
</ol>

<h2>Method 2: From Your Account Settings</h2>
<p>If you''re already logged in but want to change your password:</p>
<ol>
<li>Navigate to Settings → Security</li>
<li>Click "Change Password"</li>
<li>Enter your current password</li>
<li>Enter and confirm your new password</li>
<li>Click "Update Password"</li>
</ol>

<h2>Password Requirements</h2>
<p>Your password must meet these requirements:</p>
<ul>
<li>At least 8 characters long</li>
<li>Contains at least one uppercase letter</li>
<li>Contains at least one lowercase letter</li>
<li>Contains at least one number</li>
<li>Contains at least one special character</li>
</ul>

<h2>Troubleshooting</h2>
<p>If you don''t receive the reset email within 5 minutes, check your spam folder. Still having issues? Contact support.</p>',
'how-to', 'public', 'published', 1, 0, 0, 0, 3, 'How to Reset Your Password', 'Learn how to reset your password if you''ve forgotten it', 'password, reset, security, login', 180, DATE_ADD(NOW(), INTERVAL 180 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY)),

(3, NULL, 6, 1, 'API Authentication Guide', 'api-authentication-guide',
'Complete guide to authenticating with our API using API keys and OAuth 2.0.',
'<h2>API Authentication</h2>
<p>Our API supports two authentication methods: API Keys and OAuth 2.0. Choose the method that best fits your use case.</p>

<h2>Method 1: API Keys</h2>
<p>API Keys are the simplest way to authenticate. Best for server-to-server communication.</p>

<h3>Generating an API Key</h3>
<ol>
<li>Navigate to Settings → API Keys</li>
<li>Click "Generate New Key"</li>
<li>Give your key a descriptive name</li>
<li>Set permissions (read, write, admin)</li>
<li>Copy and securely store your key</li>
</ol>

<h3>Using API Keys</h3>
<pre><code>curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://api.example.com/v1/users</code></pre>

<h2>Method 2: OAuth 2.0</h2>
<p>OAuth 2.0 is recommended for applications that act on behalf of users.</p>

<h3>OAuth Flow</h3>
<ol>
<li>Redirect user to authorization endpoint</li>
<li>User grants permission</li>
<li>Receive authorization code</li>
<li>Exchange code for access token</li>
<li>Use access token in API requests</li>
</ol>

<h3>Example Request</h3>
<pre><code>POST /oauth/token
Content-Type: application/json

{
  "grant_type": "authorization_code",
  "code": "AUTH_CODE",
  "client_id": "YOUR_CLIENT_ID",
  "client_secret": "YOUR_CLIENT_SECRET"
}</code></pre>

<h2>Best Practices</h2>
<ul>
<li>Never commit API keys to version control</li>
<li>Rotate keys regularly</li>
<li>Use environment variables for key storage</li>
<li>Implement rate limiting in your application</li>
<li>Use HTTPS for all API requests</li>
</ul>',
'how-to', 'public', 'published', 2, 0, 0, 0, 8, 'API Authentication Guide', 'Learn how to authenticate with our API using API keys and OAuth 2.0', 'api, authentication, oauth, security, api keys', 90, DATE_ADD(NOW(), INTERVAL 90 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),

(4, NULL, 3, 1, 'Troubleshooting Login Issues', 'troubleshooting-login-issues',
'Common login problems and their solutions.',
'<h2>Common Login Issues</h2>
<p>Having trouble logging in? This guide covers the most common issues and how to resolve them.</p>

<h2>Issue: "Invalid Credentials" Error</h2>
<h3>Possible Causes</h3>
<ul>
<li>Incorrect email or password</li>
<li>Caps Lock is enabled</li>
<li>Account has not been verified</li>
</ul>
<h3>Solution</h3>
<ol>
<li>Double-check your email address for typos</li>
<li>Ensure Caps Lock is off</li>
<li>Try resetting your password</li>
<li>Check for a verification email</li>
</ol>

<h2>Issue: Account Locked</h2>
<h3>Possible Causes</h3>
<p>Too many failed login attempts (security feature)</p>
<h3>Solution</h3>
<p>Wait 15 minutes and try again, or use the "Forgot Password" option to reset immediately.</p>

<h2>Issue: Two-Factor Authentication Not Working</h2>
<h3>Possible Causes</h3>
<ul>
<li>Time sync issue on authenticator app</li>
<li>Lost access to authenticator device</li>
</ul>
<h3>Solution</h3>
<ol>
<li>Ensure your device time is set to automatic</li>
<li>Try backup codes if available</li>
<li>Contact support for account recovery</li>
</ol>

<h2>Issue: Page Keeps Redirecting</h2>
<h3>Possible Causes</h3>
<ul>
<li>Browser cookies are disabled</li>
<li>Browser cache issues</li>
</ul>
<h3>Solution</h3>
<ol>
<li>Enable cookies in browser settings</li>
<li>Clear browser cache and cookies</li>
<li>Try a different browser</li>
<li>Disable browser extensions temporarily</li>
</ol>

<h2>Still Having Issues?</h2>
<p>If none of these solutions work, please contact our support team with:</p>
<ul>
<li>Your email address</li>
<li>Browser and version</li>
<li>Screenshot of error message</li>
<li>Steps you''ve already tried</li>
</ul>',
'runbook', 'public', 'published', 1, 0, 0, 0, 4, 'Troubleshooting Login Issues', 'Solutions for common login problems and authentication issues', 'login, troubleshooting, authentication, errors', 60, DATE_ADD(NOW(), INTERVAL 60 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),

(5, NULL, 4, 1, 'Frequently Asked Questions', 'faq',
'Answers to the most commonly asked questions about our platform.',
'<h2>General Questions</h2>

<h3>What browsers are supported?</h3>
<p>We support the latest versions of Chrome, Firefox, Safari, and Edge. For the best experience, we recommend using Chrome or Firefox.</p>

<h3>Is my data secure?</h3>
<p>Yes! We use industry-standard encryption (AES-256) for data at rest and TLS 1.3 for data in transit. All servers are SOC 2 Type II certified.</p>

<h3>Can I export my data?</h3>
<p>Absolutely. You can export your data anytime from Settings → Data Export. We support CSV, JSON, and Excel formats.</p>

<h2>Account & Billing</h2>

<h3>How do I upgrade my plan?</h3>
<p>Navigate to Settings → Billing and click "Upgrade Plan". You''ll be prorated for the current billing period.</p>

<h3>What payment methods do you accept?</h3>
<p>We accept all major credit cards (Visa, MasterCard, Amex), PayPal, and ACH transfers for annual plans.</p>

<h3>Can I cancel anytime?</h3>
<p>Yes, you can cancel anytime from your account settings. You''ll retain access until the end of your billing period.</p>

<h2>Features</h2>

<h3>How many users can I have?</h3>
<p>This depends on your plan. Starter plans include 5 users, Professional includes 25, and Enterprise is unlimited.</p>

<h3>Do you offer integrations?</h3>
<p>Yes! We integrate with Slack, Microsoft Teams, Google Workspace, Salesforce, and many more. Check our integrations page for the full list.</p>

<h3>Is there a mobile app?</h3>
<p>Yes, we have mobile apps for both iOS and Android. Download them from the App Store or Google Play.</p>

<h2>Support</h2>

<h3>How can I contact support?</h3>
<p>You can reach us via email at support@example.com, live chat (available 9 AM - 5 PM EST), or by submitting a ticket through the support portal.</p>

<h3>What are your support hours?</h3>
<p>Email support is available 24/7 with response times within 4 hours. Live chat is available Monday-Friday, 9 AM - 5 PM EST.</p>',
'faq', 'public', 'published', 1, 0, 0, 0, 6, 'Frequently Asked Questions', 'Common questions and answers about our platform', 'faq, questions, help, support', 120, DATE_ADD(NOW(), INTERVAL 120 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),

(6, NULL, 5, 1, 'Security Best Practices', 'security-best-practices',
'Essential security practices to keep your account and data safe.',
'<h2>Account Security</h2>

<h3>1. Use Strong Passwords</h3>
<p>Create passwords that are:</p>
<ul>
<li>At least 12 characters long</li>
<li>A mix of uppercase, lowercase, numbers, and symbols</li>
<li>Unique to this account (don''t reuse passwords)</li>
<li>Not based on personal information</li>
</ul>
<p><strong>Pro tip:</strong> Use a password manager like 1Password or LastPass.</p>

<h3>2. Enable Two-Factor Authentication (2FA)</h3>
<p>Add an extra layer of security by requiring both your password and a verification code. We support:</p>
<ul>
<li>Authenticator apps (Google Authenticator, Authy)</li>
<li>SMS codes</li>
<li>Hardware security keys (YubiKey)</li>
</ul>

<h3>3. Review Active Sessions</h3>
<p>Regularly check Settings → Security → Active Sessions and revoke any unfamiliar devices.</p>

<h2>Data Protection</h2>

<h3>4. Be Careful with Permissions</h3>
<p>Follow the principle of least privilege:</p>
<ul>
<li>Only grant necessary permissions to users</li>
<li>Review user access quarterly</li>
<li>Remove access immediately when team members leave</li>
</ul>

<h3>5. Encrypt Sensitive Data</h3>
<p>For highly sensitive information, use additional encryption before uploading to the platform.</p>

<h3>6. Regular Backups</h3>
<p>While we back up your data, maintain your own backups for critical information. Use our automated export feature.</p>

<h2>Access Control</h2>

<h3>7. Use SSO When Available</h3>
<p>Single Sign-On (SSO) centralizes authentication and makes it easier to manage access across your organization.</p>

<h3>8. Implement IP Whitelisting</h3>
<p>Enterprise plans can restrict access to specific IP addresses or ranges for enhanced security.</p>

<h3>9. Monitor Audit Logs</h3>
<p>Regularly review audit logs in Settings → Security → Audit Log to detect suspicious activity.</p>

<h2>Application Security</h2>

<h3>10. Keep API Keys Secure</h3>
<ul>
<li>Never commit keys to version control</li>
<li>Use environment variables</li>
<li>Rotate keys regularly (every 90 days)</li>
<li>Delete unused keys immediately</li>
</ul>

<h3>11. Validate Webhooks</h3>
<p>Always verify webhook signatures to ensure requests are from our platform.</p>

<h2>Incident Response</h2>

<h3>What to Do If Compromised</h3>
<ol>
<li>Immediately change your password</li>
<li>Revoke all API keys</li>
<li>Review audit logs for unauthorized access</li>
<li>Contact support immediately</li>
<li>Enable 2FA if not already active</li>
</ol>',
'general', 'public', 'published', 1, 0, 0, 0, 7, 'Security Best Practices', 'Essential security practices to protect your account and data', 'security, best practices, passwords, 2fa, encryption', 60, DATE_ADD(NOW(), INTERVAL 60 DAY), NOW(), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY));

-- Insert KB Articles (Draft and In Review)
INSERT IGNORE INTO kb_articles (id, company_id, category_id, author_id, title, slug, summary, content_html, template_type, visibility, status, version, reading_time_minutes, created_at, updated_at) VALUES
(7, NULL, 2, 1, 'How to Set Up Webhooks', 'how-to-set-up-webhooks',
'Learn how to configure webhooks to receive real-time notifications about events.',
'<h2>What are Webhooks?</h2>
<p>Webhooks allow your application to receive real-time notifications when specific events occur in the platform.</p>

<h2>Setting Up a Webhook</h2>
<p>Content being written...</p>',
'how-to', 'public', 'draft', 1, 10, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),

(8, NULL, 2, 1, 'Configuring SSO with SAML', 'configuring-sso-with-saml',
'Step-by-step guide to setting up Single Sign-On using SAML 2.0.',
'<h2>Prerequisites</h2>
<p>Before you begin, ensure you have:</p>
<ul>
<li>An Enterprise plan</li>
<li>Access to your identity provider (IdP)</li>
<li>Administrative privileges</li>
</ul>

<h2>Configuration Steps</h2>
<p>Full guide coming soon...</p>',
'how-to', 'public', 'in_review', 1, 15, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Insert KB Articles (Company-specific for Acme Corp)
INSERT IGNORE INTO kb_articles (id, company_id, category_id, author_id, title, slug, summary, content_html, template_type, visibility, status, version, reading_time_minutes, published_at, created_at, updated_at) VALUES
(9, 1, 9, 3, 'Remote Work Policy', 'remote-work-policy',
'Company policy regarding remote work arrangements and expectations.',
'<h2>Overview</h2>
<p>Acme Corporation supports flexible work arrangements including remote work options for eligible employees.</p>

<h2>Eligibility</h2>
<p>Employees must:</p>
<ul>
<li>Have completed 90-day probation period</li>
<li>Have a role suitable for remote work</li>
<li>Maintain good performance ratings</li>
<li>Have manager approval</li>
</ul>

<h2>Requirements</h2>
<h3>Home Office Setup</h3>
<ul>
<li>Dedicated workspace</li>
<li>Reliable high-speed internet (minimum 50 Mbps)</li>
<li>Appropriate ergonomic setup</li>
</ul>

<h3>Availability</h3>
<p>Remote employees must:</p>
<ul>
<li>Be available during core hours (10 AM - 3 PM local time)</li>
<li>Respond to messages within 2 hours during work hours</li>
<li>Attend all required meetings (virtual or in-person)</li>
</ul>

<h2>Equipment</h2>
<p>The company provides:</p>
<ul>
<li>Laptop and necessary peripherals</li>
<li>Monthly internet stipend ($50)</li>
<li>Ergonomic equipment budget ($500 one-time)</li>
</ul>

<h2>Security</h2>
<p>All remote workers must:</p>
<ul>
<li>Use VPN for all company system access</li>
<li>Keep devices encrypted and password-protected</li>
<li>Not share equipment with family members</li>
<li>Follow all company security policies</li>
</ul>',
'general', 'private_company', 'published', 1, 4, NOW(), DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),

(10, 1, 10, 3, 'Time Off Request Process', 'time-off-request-process',
'How to request and manage time off in the HR system.',
'<h2>Requesting Time Off</h2>
<ol>
<li>Log into the employee portal</li>
<li>Navigate to Time Off → New Request</li>
<li>Select the type of leave</li>
<li>Choose start and end dates</li>
<li>Add a brief reason</li>
<li>Submit for manager approval</li>
</ol>

<h2>Time Off Types</h2>
<ul>
<li><strong>Vacation:</strong> 15 days per year (accrued monthly)</li>
<li><strong>Sick Leave:</strong> 10 days per year</li>
<li><strong>Personal Days:</strong> 3 days per year</li>
<li><strong>Holidays:</strong> 10 company holidays</li>
</ul>

<h2>Approval Process</h2>
<p>Requests are reviewed in this order:</p>
<ol>
<li>Direct manager (within 2 business days)</li>
<li>HR review (for extended leave &gt; 5 days)</li>
<li>Final confirmation sent via email</li>
</ol>

<h2>Important Notes</h2>
<ul>
<li>Submit requests at least 2 weeks in advance when possible</li>
<li>Emergency sick leave can be requested same-day</li>
<li>Unused vacation can be carried over (max 5 days)</li>
<li>Check team calendar before requesting popular dates</li>
</ul>',
'how-to', 'private_company', 'published', 1, 3, NOW(), DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY));

-- Insert KB Article Tags
INSERT IGNORE INTO kb_article_tags (article_id, tag_id) VALUES
(1, 4), (1, 7), -- Getting Started: setup, beginner
(2, 1), (2, 2), (2, 7), -- Password Reset: authentication, security, beginner
(3, 1), (3, 2), (3, 3), (3, 8), -- API Auth: authentication, security, api, advanced
(4, 1), (4, 6), (4, 7), -- Login Issues: authentication, troubleshooting, beginner
(5, 7), -- FAQ: beginner
(6, 2), (6, 8), -- Security: security, advanced
(7, 3), (7, 9), (7, 8), -- Webhooks: api, integration, advanced
(8, 1), (8, 2), (8, 5), (8, 8), -- SSO: authentication, security, configuration, advanced
(9, 5), -- Remote Work: configuration
(10, 7); -- Time Off: beginner

-- Insert KB Collections
INSERT IGNORE INTO kb_collections (id, company_id, name, slug, description, is_visible, sort_order) VALUES
(1, NULL, 'New User Onboarding', 'new-user-onboarding', 'Essential articles for new users getting started', 1, 1),
(2, NULL, 'Developer Resources', 'developer-resources', 'Technical documentation for developers', 1, 2),
(3, NULL, 'Security & Compliance', 'security-compliance', 'Security best practices and compliance information', 1, 3),
(4, 1, 'Employee Handbook', 'employee-handbook', 'Company policies and procedures for Acme employees', 1, 1);

-- Insert KB Collection Articles
INSERT IGNORE INTO kb_collection_articles (collection_id, article_id, sort_order) VALUES
(1, 1, 1), -- New User: Getting Started
(1, 2, 2), -- New User: Password Reset
(1, 5, 3), -- New User: FAQ
(2, 3, 1), -- Developer: API Auth
(2, 7, 2), -- Developer: Webhooks (draft)
(2, 8, 3), -- Developer: SSO (in review)
(3, 6, 1), -- Security: Best Practices
(3, 3, 2), -- Security: API Auth
(4, 9, 1), -- Employee: Remote Work
(4, 10, 2); -- Employee: Time Off

-- Insert KB Article Versions (for articles that have been published)
INSERT IGNORE INTO kb_article_versions (id, article_id, version_number, title, content_html, content_markdown, published_by, change_summary, created_at) VALUES
(1, 1, 1, 'Getting Started with Your Account',
'<h2>Welcome!</h2><p>This guide will help you get started...</p>',
'## Welcome!\n\nThis guide will help you get started...',
1, 'Initial publication', DATE_SUB(NOW(), INTERVAL 30 DAY)),

(2, 2, 1, 'How to Reset Your Password',
'<h2>Resetting Your Password</h2><p>If you''ve forgotten...</p>',
'## Resetting Your Password\n\nIf you''ve forgotten...',
1, 'Initial publication', DATE_SUB(NOW(), INTERVAL 25 DAY)),

(3, 3, 1, 'API Authentication Guide',
'<h2>API Authentication</h2><p>Our API supports...</p>',
'## API Authentication\n\nOur API supports...',
1, 'Initial publication', DATE_SUB(NOW(), INTERVAL 20 DAY)),

(4, 3, 2, 'API Authentication Guide',
'<h2>API Authentication</h2><p>Our API supports two authentication methods...</p>',
'## API Authentication\n\nOur API supports two authentication methods...',
1, 'Added OAuth 2.0 section with code examples', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Insert KB Audit Log
INSERT IGNORE INTO kb_audit_log (id, article_id, user_id, action, version_number, changes, ip_address, created_at) VALUES
(1, 1, 1, 'created', NULL, '{"status": "draft"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 31 DAY)),
(2, 1, 1, 'published', 1, '{"status": "published"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 2, 1, 'created', NULL, '{"status": "draft"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 26 DAY)),
(4, 2, 1, 'published', 1, '{"status": "published"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(5, 3, 1, 'created', NULL, '{"status": "draft"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 21 DAY)),
(6, 3, 1, 'published', 1, '{"status": "published"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(7, 3, 1, 'updated', 2, '{"added": "OAuth 2.0 documentation"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(8, 4, 1, 'created', NULL, '{"status": "draft"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 16 DAY)),
(9, 4, 1, 'published', 1, '{"status": "published"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(10, 7, 1, 'created', NULL, '{"status": "draft"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(11, 8, 1, 'created', NULL, '{"status": "draft"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(12, 8, 1, 'updated', NULL, '{"status": "in_review"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Insert KB Article Views (simulated traffic)
INSERT IGNORE INTO kb_article_views (id, article_id, ip_address, user_agent, created_at) VALUES
(1, 1, '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(2, 1, '192.168.1.11', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, 1, '192.168.1.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/121.0', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 2, '192.168.1.13', 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0.0.0', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(5, 2, '192.168.1.14', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(6, 3, '192.168.1.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(7, 3, '192.168.1.16', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
(8, 4, '192.168.1.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edge/120.0.0.0', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(9, 5, '192.168.1.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
(10, 6, '192.168.1.19', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', DATE_SUB(NOW(), INTERVAL 10 HOUR));

-- Insert KB Article Feedback
INSERT IGNORE INTO kb_article_feedback (id, article_id, is_helpful, comment, ip_address, created_at) VALUES
(1, 1, 1, 'Very clear and easy to follow!', '192.168.1.10', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(2, 1, 1, NULL, '192.168.1.11', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, 2, 1, 'Solved my problem immediately', '192.168.1.13', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(4, 3, 0, 'Could use more code examples', '192.168.1.15', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(5, 3, 1, 'Comprehensive guide, very helpful', '192.168.1.16', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
(6, 4, 1, 'Fixed my login issue, thanks!', '192.168.1.17', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(7, 5, 1, NULL, '192.168.1.18', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
(8, 6, 1, 'Important security info, everyone should read this', '192.168.1.19', DATE_SUB(NOW(), INTERVAL 10 HOUR));

-- Insert KB Search Queries
INSERT IGNORE INTO kb_search_queries (id, company_id, query, results_count, ip_address, created_at) VALUES
(1, NULL, 'password reset', 1, '192.168.1.20', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, NULL, 'api authentication', 1, '192.168.1.21', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, NULL, 'login problem', 1, '192.168.1.22', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, NULL, 'getting started', 1, '192.168.1.23', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, NULL, 'security best practices', 1, '192.168.1.24', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, NULL, 'webhook setup', 0, '192.168.1.25', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(7, 1, 'remote work', 1, '192.168.1.26', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(8, 1, 'time off', 1, '192.168.1.27', DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Update article view counts based on inserted views
UPDATE kb_articles SET view_count = (SELECT COUNT(*) FROM kb_article_views WHERE article_id = kb_articles.id) WHERE id IN (1,2,3,4,5,6);

-- Update article helpful counts based on feedback
UPDATE kb_articles SET
    helpful_count = (SELECT COUNT(*) FROM kb_article_feedback WHERE article_id = kb_articles.id AND is_helpful = 1),
    not_helpful_count = (SELECT COUNT(*) FROM kb_article_feedback WHERE article_id = kb_articles.id AND is_helpful = 0)
WHERE id IN (1,2,3,4,5,6);

-- ============================================================
-- CONTENT MODERATION DUMMY DATA
-- ============================================================

-- Insert Blacklisted Words
INSERT IGNORE INTO blacklisted_words (id, word, severity, category, added_by, notes, is_active) VALUES
(1, 'spam', 'medium', 'spam', 1, 'Common spam word', 1),
(2, 'viagra', 'high', 'spam', 1, 'Pharmaceutical spam', 1),
(3, 'casino', 'medium', 'spam', 1, 'Gambling spam', 1),
(4, 'fuck', 'high', 'profanity', 1, 'Strong profanity', 1),
(5, 'shit', 'medium', 'profanity', 1, 'Common profanity', 1),
(6, 'bitch', 'medium', 'profanity', 1, 'Offensive term', 1),
(7, 'ass', 'low', 'profanity', 1, 'Mild profanity', 1),
(8, 'nigger', 'critical', 'hate-speech', 1, 'Racial slur', 1),
(9, 'faggot', 'critical', 'hate-speech', 1, 'Homophobic slur', 1),
(10, 'kike', 'critical', 'hate-speech', 1, 'Antisemitic slur', 1),
(11, 'chink', 'critical', 'hate-speech', 1, 'Racial slur', 1),
(12, 'retard', 'high', 'hate-speech', 1, 'Ableist slur', 1),
(13, 'xxx', 'high', 'explicit', 1, 'Explicit content marker', 1),
(14, 'porn', 'high', 'explicit', 1, 'Explicit content', 1),
(15, 'sex', 'medium', 'explicit', 1, 'Potentially explicit', 1);

-- Insert Blacklist Audit Log
INSERT IGNORE INTO blacklist_audit_log (id, blacklist_id, word, action, user_id, changes, ip_address, created_at) VALUES
(1, 1, 'spam', 'added', 1, '{"severity": "medium", "category": "spam"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 2, 'viagra', 'added', 1, '{"severity": "high", "category": "spam"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 3, 'casino', 'added', 1, '{"severity": "medium", "category": "spam"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 4, 'fuck', 'added', 1, '{"severity": "high", "category": "profanity"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(5, 5, 'shit', 'added', 1, '{"severity": "medium", "category": "profanity"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 6, 'bitch', 'added', 1, '{"severity": "medium", "category": "profanity"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 29 DAY)),
(7, 7, 'ass', 'added', 1, '{"severity": "low", "category": "profanity"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 28 DAY)),
(8, 8, 'nigger', 'added', 1, '{"severity": "critical", "category": "hate-speech"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 28 DAY)),
(9, 9, 'faggot', 'added', 1, '{"severity": "critical", "category": "hate-speech"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 27 DAY)),
(10, 10, 'kike', 'added', 1, '{"severity": "critical", "category": "hate-speech"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 27 DAY)),
(11, 11, 'chink', 'added', 1, '{"severity": "critical", "category": "hate-speech"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 26 DAY)),
(12, 12, 'retard', 'added', 1, '{"severity": "high", "category": "hate-speech"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 26 DAY)),
(13, 13, 'xxx', 'added', 1, '{"severity": "high", "category": "explicit"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(14, 14, 'porn', 'added', 1, '{"severity": "high", "category": "explicit"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(15, 15, 'sex', 'added', 1, '{"severity": "medium", "category": "explicit"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 24 DAY));

-- ============================================================
-- HELPDESK DUMMY DATA
-- ============================================================

-- Insert Ticket Priorities
INSERT IGNORE INTO ticket_priorities (id, name, level, color, is_default) VALUES
(1, 'Low', 1, '#28a745', FALSE),
(2, 'Medium', 2, '#ffc107', TRUE),
(3, 'High', 3, '#fd7e14', FALSE),
(4, 'Urgent', 4, '#dc3545', FALSE),
(5, 'Critical', 5, '#6f42c1', FALSE);

-- Insert Ticket Queues
INSERT IGNORE INTO ticket_queues (id, company_id, name, slug, description, email, color, icon, sort_order, is_active, sla_first_response_hours, sla_resolution_hours) VALUES
(1, 1, 'First Line Support', 'first-line', 'Initial customer support and basic troubleshooting', 'support@acme-corp.com', '#3498db', 'headset', 1, 1, 2, 24),
(2, 1, 'Second Line Support', 'second-line', 'Advanced technical support and escalations', 'support-l2@acme-corp.com', '#e74c3c', 'tools', 2, 1, 4, 48),
(3, 1, 'Back Office', 'back-office', 'Administrative and billing support', 'backoffice@acme-corp.com', '#9b59b6', 'building', 3, 1, 8, 72),
(4, 1, 'IT Department', 'it-department', 'Internal IT requests and system issues', 'it@acme-corp.com', '#34495e', 'cpu', 4, 1, 1, 8);

-- Insert Queue Staff Assignments
-- User 3 (John Smith - VP Engineering) - All queues
INSERT IGNORE INTO queue_staff (queue_id, user_id, can_assign, can_view, can_respond) VALUES
(1, 3, 1, 1, 1),
(2, 3, 1, 1, 1),
(3, 3, 1, 1, 1),
(4, 3, 1, 1, 1);

-- User 4 (Emily Johnson - Senior Software Engineer) - First and Second Line
INSERT IGNORE INTO queue_staff (queue_id, user_id, can_assign, can_view, can_respond) VALUES
(1, 4, 1, 1, 1),
(2, 4, 1, 1, 1);

-- User 5 (Michael Brown) - First Line only
INSERT IGNORE INTO queue_staff (queue_id, user_id, can_assign, can_view, can_respond) VALUES
(1, 5, 1, 1, 1);

-- User 13 (William White) - Back Office only
INSERT IGNORE INTO queue_staff (queue_id, user_id, can_assign, can_view, can_respond) VALUES
(3, 13, 1, 1, 1);

-- User 15 (Richard Clark) - IT Department and Second Line
INSERT IGNORE INTO queue_staff (queue_id, user_id, can_assign, can_view, can_respond) VALUES
(2, 15, 1, 1, 1),
(4, 15, 1, 1, 1);

-- Insert Ticket Tags
INSERT IGNORE INTO ticket_tags (id, company_id, name, slug, color) VALUES
(1, 1, 'Bug', 'bug', '#dc3545'),
(2, 1, 'Feature Request', 'feature-request', '#17a2b8'),
(3, 1, 'Question', 'question', '#ffc107'),
(4, 1, 'Urgent', 'urgent', '#fd7e14'),
(5, 1, 'Billing', 'billing', '#6f42c1'),
(6, 1, 'Account', 'account', '#20c997'),
(7, 1, 'Technical', 'technical', '#e83e8c');

-- Insert Ticket Templates
INSERT IGNORE INTO ticket_templates (id, company_id, queue_id, name, subject, content, is_global, created_by) VALUES
(1, 1, 1, 'Welcome and Initial Response', 'Re: {{ticket.subject}}',
'Hello {{ticket.contact_name}},

Thank you for contacting Acme Corporation support. We have received your request (Ticket #{{ticket.ticket_number}}) and one of our team members will respond shortly.

Your ticket has been assigned to our {{queue.name}} team.

In the meantime, you may find these resources helpful:
- Knowledge Base: https://acme-corp.com/kb
- FAQ: https://acme-corp.com/faq

Best regards,
Acme Support Team', 1, 3),

(2, 1, NULL, 'Password Reset Instructions', 'Password Reset Instructions',
'Hello {{ticket.contact_name}},

Here are the steps to reset your password:

1. Go to https://acme-corp.com/forgot-password
2. Enter your email address
3. Check your inbox for the reset link
4. Click the link and create a new password
5. Log in with your new password

If you continue to have issues, please reply to this ticket and we''ll assist you further.

Best regards,
{{staff.name}}', 1, 3),

(3, 1, NULL, 'Ticket Resolved', 'Your ticket has been resolved',
'Hello {{ticket.contact_name}},

We''re happy to inform you that your support ticket (#{{ticket.ticket_number}}) has been resolved.

Resolution: {{ticket.resolution_notes}}

If you have any additional questions or if this issue reoccurs, please don''t hesitate to reach out by replying to this email or creating a new ticket.

We value your feedback! If you have a moment, please let us know how we did.

Best regards,
{{staff.name}}
Acme Corporation Support', 1, 3),

(4, 1, 3, 'Billing Inquiry Response', 'Re: Billing Inquiry',
'Hello {{ticket.contact_name}},

Thank you for reaching out regarding your billing inquiry.

Our billing team is reviewing your account and will provide detailed information within 24 hours. We appreciate your patience.

If you have additional billing documents or information to share, please reply to this email with the attachments.

Best regards,
{{staff.name}}
Back Office Team', 0, 13),

(5, 1, NULL, 'Escalation Notice', 'Your ticket has been escalated',
'Hello {{ticket.contact_name}},

Your support ticket (#{{ticket.ticket_number}}) has been escalated to our {{queue.name}} team for further assistance.

A senior team member will review your case and respond as soon as possible.

Thank you for your patience.

Best regards,
Acme Corporation Support', 1, 3);

-- Insert Sample Tickets
INSERT IGNORE INTO tickets (id, ticket_number, company_id, queue_id, priority_id, contact_name, contact_email, contact_phone, user_id, subject, description, status, assigned_to, source, created_at, updated_at) VALUES
(1, 'TKT-2025-00001', 1, 1, 2, 'Sarah Williams', 'sarah.williams@example.com', '+1-555-1234', NULL,
'Cannot login to my account',
'Hello,

I am trying to log into my account but I keep getting an "Invalid credentials" error. I am sure I am using the correct password. I tried resetting it but I did not receive the email.

Can you please help?

Thanks,
Sarah',
'resolved', 4, 'web', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),

(2, 'TKT-2025-00002', 1, 1, 3, 'Robert Chen', 'robert.chen@example.com', '+1-555-5678', NULL,
'Error when uploading files',
'I am getting an error message when trying to upload PDF files to the system. The error says "File type not supported" but PDFs should be supported according to your documentation.

Error screenshot attached.',
'open', 5, 'web', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),

(3, 'TKT-2025-00003', 1, 3, 1, 'Jennifer Martinez', 'jennifer.m@example.com', '+1-555-9012', NULL,
'Question about my invoice',
'Hi,

I received my invoice for January but I notice there are charges I do not recognize. Can someone from billing please review this?

Invoice #: INV-2025-001
Amount in question: $150.00

Thank you',
'open', 13, 'email', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),

(4, 'TKT-2025-00004', 1, 2, 4, 'Michael Johnson', 'michael.j@techco.com', '+1-555-3456', NULL,
'API returning 500 errors',
'Our production application started receiving 500 errors from your API endpoint /api/v1/users at approximately 10:30 AM EST today.

This is impacting our business operations. Please prioritize this.

Error log:
[ERROR] 500 Internal Server Error at /api/v1/users
Timestamp: 2025-01-07 10:32:15

Urgent assistance needed.',
'open', 15, 'email', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),

(5, 'TKT-2025-00005', 1, 1, 2, 'Lisa Anderson', 'l.anderson@startup.io', '+1-555-7890', NULL,
'How do I add users to my account?',
'Hello,

I am new to the platform and I would like to add team members to my account. Where can I find this option?

I looked in Settings but could not find it.

Thank you!',
'resolved', 5, 'web', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),

(6, 'TKT-2025-00006', 1, 1, 2, 'David Thompson', 'dthompson@example.com', NULL, NULL,
'Feature Request: Dark Mode',
'Hi Acme team,

Would it be possible to add a dark mode to the application? Many users work late hours and would appreciate this feature.

Thanks for considering!',
'new', NULL, 'web', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),

(7, 'TKT-2025-00007', 1, 4, 3, 'Mary Jackson', 'mary.jackson@acme-corp.com', '+1-555-1000', 12,
'Laptop not connecting to WiFi',
'My laptop is not connecting to the office WiFi network. I can see the network but it says "Cannot connect".

Location: 3rd Floor, Conference Room B
Device: Dell Latitude 5420

I need this resolved for an important client meeting at 2 PM today.

Thanks,
Mary',
'open', 15, 'web', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- Update resolved tickets
UPDATE tickets SET
    resolved_by = 4,
    resolved_at = DATE_SUB(NOW(), INTERVAL 3 DAY),
    resolution_notes = 'Password reset email was in spam folder. User was able to reset password successfully and log in. Advised to add noreply@acme-corp.com to safe senders list.',
    first_response_at = DATE_SUB(NOW(), INTERVAL 5 DAY),
    last_staff_reply_at = DATE_SUB(NOW(), INTERVAL 3 DAY)
WHERE id = 1;

UPDATE tickets SET
    resolved_by = 5,
    resolved_at = DATE_SUB(NOW(), INTERVAL 4 DAY),
    resolution_notes = 'User needed to add team members through Team Management page in Settings. Provided step-by-step instructions.',
    first_response_at = DATE_SUB(NOW(), INTERVAL 4 DAY),
    last_staff_reply_at = DATE_SUB(NOW(), INTERVAL 4 DAY)
WHERE id = 5;

-- Update tickets with first response
UPDATE tickets SET
    first_response_at = DATE_SUB(NOW(), INTERVAL 3 DAY),
    last_staff_reply_at = DATE_SUB(NOW(), INTERVAL 3 DAY)
WHERE id = 2;

UPDATE tickets SET
    first_response_at = DATE_SUB(NOW(), INTERVAL 2 DAY),
    last_staff_reply_at = DATE_SUB(NOW(), INTERVAL 2 DAY)
WHERE id = 3;

UPDATE tickets SET
    first_response_at = DATE_SUB(NOW(), INTERVAL 1 DAY),
    last_staff_reply_at = DATE_SUB(NOW(), INTERVAL 1 DAY)
WHERE id = 4;

UPDATE tickets SET
    first_response_at = DATE_SUB(NOW(), INTERVAL 3 HOUR),
    last_staff_reply_at = DATE_SUB(NOW(), INTERVAL 2 HOUR)
WHERE id = 7;

-- Insert Ticket Replies
INSERT IGNORE INTO ticket_replies (id, ticket_id, user_id, author_name, author_email, is_staff, is_private, message, source, created_at) VALUES
-- Ticket 1 replies
(1, 1, 4, 'Emily Johnson', 'emily.johnson@acme-corp.com', 1, 0,
'Hello Sarah,

Thank you for contacting Acme support. I''m sorry to hear you''re having trouble logging in.

Could you please check your spam/junk folder for the password reset email? Sometimes our emails end up there.

Also, please confirm the email address you''re trying to use to log in.

Best regards,
Emily Johnson
Acme Support Team', 'web', DATE_SUB(NOW(), INTERVAL 5 DAY)),

(2, 1, NULL, 'Sarah Williams', 'sarah.williams@example.com', 0, 0,
'Hi Emily,

You were right! The password reset email was in my spam folder. I was able to reset my password and I can log in now.

Thank you so much for your help!

Sarah', 'email', DATE_SUB(NOW(), INTERVAL 4 DAY)),

(3, 1, 4, 'Emily Johnson', 'emily.johnson@acme-corp.com', 1, 0,
'Great to hear, Sarah! I''m glad we could resolve this quickly.

I recommend adding noreply@acme-corp.com to your safe senders list to prevent this from happening again.

I''ll mark this ticket as resolved. Feel free to reach out if you need anything else!

Best regards,
Emily', 'web', DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- Ticket 2 replies
(4, 2, 5, 'Michael Brown', 'michael.brown@acme-corp.com', 1, 0,
'Hello Robert,

Thank you for reporting this issue. I can see you''re trying to upload PDF files.

Could you please provide:
1. The size of the PDF file you''re trying to upload
2. The exact error message (screenshot would be helpful)
3. What browser you''re using

This will help us troubleshoot the issue.

Best regards,
Michael Brown
Acme Support Team', 'web', DATE_SUB(NOW(), INTERVAL 3 DAY)),

(5, 2, NULL, 'Robert Chen', 'robert.chen@example.com', 0, 0,
'Hi Michael,

Here are the details:
1. File size: 2.3 MB
2. Error message screenshot is attached to my original message
3. Browser: Chrome Version 120.0.6099.130

Let me know if you need anything else.

Thanks,
Robert', 'email', DATE_SUB(NOW(), INTERVAL 2 DAY)),

(6, 2, 5, 'Michael Brown', 'michael.brown@acme-corp.com', 1, 1,
'Note to team: This appears to be related to the MIME type validation issue we discovered last week. Escalating to Second Line.',
'web', DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Ticket 3 replies
(7, 3, 13, 'William White', 'william.white@acme-corp.com', 1, 0,
'Hello Jennifer,

Thank you for reaching out regarding your invoice.

I am reviewing Invoice #INV-2025-001 now. I will need 24 hours to complete the review and will provide you with a detailed breakdown.

If you have any receipts or documentation related to the charges in question, please reply with them attached.

Best regards,
William White
Back Office Team', 'web', DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Ticket 4 replies
(8, 4, 15, 'Richard Clark', 'richard.clark@acme-corp.com', 1, 0,
'Hello Michael,

Thank you for reporting this critical issue. We are investigating the 500 errors immediately.

Our engineering team has been notified and is looking into the /api/v1/users endpoint.

I will provide updates every hour until this is resolved.

Initial findings: We see elevated error rates starting at 10:30 AM EST. Investigating database connection issues.

Best regards,
Richard Clark
Technical Support Team', 'web', DATE_SUB(NOW(), INTERVAL 1 DAY)),

(9, 4, 15, 'Richard Clark', 'richard.clark@acme-corp.com', 1, 1,
'Internal note: Database connection pool was exhausted. DBA team is scaling up connections. ETA 30 minutes.',
'web', DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Ticket 5 replies
(10, 5, 5, 'Michael Brown', 'michael.brown@acme-corp.com', 1, 0,
'Hello Lisa,

Welcome to Acme! I''d be happy to help you add team members.

Here are the steps:
1. Log into your account
2. Click on your name in the top right corner
3. Select "Team Management" from the dropdown
4. Click "Invite Team Member"
5. Enter their email address and select their role
6. Click "Send Invitation"

They will receive an email with instructions to create their account.

You can find more information in our Knowledge Base: https://acme-corp.com/kb/add-team-members

Let me know if you need any help!

Best regards,
Michael', 'web', DATE_SUB(NOW(), INTERVAL 4 DAY)),

(11, 5, NULL, 'Lisa Anderson', 'l.anderson@startup.io', 0, 0,
'Perfect! I found it. I was looking in the wrong place.

Just successfully invited my first team member. Thank you so much!

Lisa', 'email', DATE_SUB(NOW(), INTERVAL 4 DAY)),

-- Ticket 7 replies
(12, 7, 15, 'Richard Clark', 'richard.clark@acme-corp.com', 1, 0,
'Hi Mary,

I''m on my way to Conference Room B now to take a look at your laptop.

Should be there in 5 minutes.

Richard
IT Support', 'web', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Insert Ticket Tags Assignments
INSERT IGNORE INTO ticket_tag_assignments (ticket_id, tag_id) VALUES
(1, 3), -- Question
(1, 6), -- Account
(2, 1), -- Bug
(2, 7), -- Technical
(3, 5), -- Billing
(3, 3), -- Question
(4, 1), -- Bug
(4, 4), -- Urgent
(4, 7), -- Technical
(5, 3), -- Question
(6, 2), -- Feature Request
(7, 4), -- Urgent
(7, 7); -- Technical

-- Insert Ticket Watchers
INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES
(4, 3), -- John Smith watching urgent API issue
(7, 3); -- John Smith watching internal IT ticket

-- Insert Ticket CC
INSERT IGNORE INTO ticket_cc (ticket_id, email, name, added_by) VALUES
(4, 'ops-team@techco.com', 'TechCo Operations Team', 15);

-- Insert Activity Log
INSERT IGNORE INTO ticket_activity_log (ticket_id, user_id, action, field_name, old_value, new_value, description, created_at) VALUES
(1, NULL, 'created', NULL, NULL, NULL, 'Ticket created by Sarah Williams', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 4, 'assigned', 'assigned_to', NULL, '4', 'Ticket assigned to Emily Johnson', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 4, 'status_changed', 'status', 'new', 'open', 'Status changed from New to Open', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 4, 'resolved', 'status', 'open', 'resolved', 'Ticket marked as resolved', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, NULL, 'created', NULL, NULL, NULL, 'Ticket created by Robert Chen', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 5, 'assigned', 'assigned_to', NULL, '5', 'Ticket assigned to Michael Brown', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, NULL, 'created', NULL, NULL, NULL, 'Ticket created by Jennifer Martinez', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 13, 'assigned', 'assigned_to', NULL, '13', 'Ticket assigned to William White', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, NULL, 'created', NULL, NULL, NULL, 'Ticket created by Michael Johnson', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 15, 'assigned', 'assigned_to', NULL, '15', 'Ticket assigned to Richard Clark', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 15, 'priority_changed', 'priority_id', '2', '4', 'Priority changed from Medium to Urgent', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, NULL, 'created', NULL, NULL, NULL, 'Ticket created by Lisa Anderson', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 5, 'assigned', 'assigned_to', NULL, '5', 'Ticket assigned to Michael Brown', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 5, 'resolved', 'status', 'open', 'resolved', 'Ticket marked as resolved', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(6, NULL, 'created', NULL, NULL, NULL, 'Ticket created by David Thompson', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(7, NULL, 'created', NULL, NULL, NULL, 'Ticket created by Mary Jackson', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(7, 15, 'assigned', 'assigned_to', NULL, '15', 'Ticket assigned to Richard Clark', DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- Note: Password for all users is 'password' (hashed with bcrypt)
-- ============================================================
-- END OF DUMMY DATA
-- Safe to run multiple times - duplicates will be skipped
-- ============================================================

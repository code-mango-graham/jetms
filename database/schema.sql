-- School year to curriculum linkage
ALTER TABLE tbl_schoolyear
	ADD COLUMN IF NOT EXISTS curriculum_id INT NULL AFTER schoolyear_name,
	ADD INDEX IF NOT EXISTS idx_schoolyear_curriculum_id (curriculum_id);

-- Sections that belong to a specific school year + level
CREATE TABLE IF NOT EXISTS tbl_schoolyear_section (
	schoolyear_section_id INT AUTO_INCREMENT PRIMARY KEY,
	schoolyear_id INT NOT NULL,
	level_id INT NOT NULL,
	section_name VARCHAR(100) NOT NULL,
	section_remarks TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_sy_level_section (schoolyear_id, level_id, section_name),
	KEY idx_sy_section_schoolyear (schoolyear_id),
	KEY idx_sy_section_level (level_id),
	CONSTRAINT fk_sy_section_schoolyear
		FOREIGN KEY (schoolyear_id)
		REFERENCES tbl_schoolyear(schoolyear_id)
		ON DELETE CASCADE
		ON UPDATE CASCADE,
	CONSTRAINT fk_sy_section_level
		FOREIGN KEY (level_id)
		REFERENCES tbl_level(level_id)
		ON DELETE CASCADE
		ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Subject assignment per school-year section
CREATE TABLE IF NOT EXISTS tbl_schoolyear_section_subject (
	sy_section_subject_id INT AUTO_INCREMENT PRIMARY KEY,
	schoolyear_section_id INT NOT NULL,
	subject_id INT NOT NULL,
	teacher_id INT NULL,
	schedule_info VARCHAR(255) NULL,
	room_no VARCHAR(50) NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_sy_section_subject (schoolyear_section_id, subject_id),
	KEY idx_sy_subject_section (schoolyear_section_id),
	KEY idx_sy_subject_subject (subject_id),
	KEY idx_sy_subject_teacher (teacher_id),
	CONSTRAINT fk_sy_subject_section
		FOREIGN KEY (schoolyear_section_id)
		REFERENCES tbl_schoolyear_section(schoolyear_section_id)
		ON DELETE CASCADE
		ON UPDATE CASCADE,
	CONSTRAINT fk_sy_subject_subject
		FOREIGN KEY (subject_id)
		REFERENCES tbl_subject(subject_id)
		ON DELETE CASCADE
		ON UPDATE CASCADE,
	CONSTRAINT fk_sy_subject_teacher
		FOREIGN KEY (teacher_id)
		REFERENCES tbl_teacher(teacher_id)
		ON DELETE SET NULL
		ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Students table
CREATE TABLE IF NOT EXISTS tbl_student (
	student_id INT AUTO_INCREMENT PRIMARY KEY,
	lrn VARCHAR(20) UNIQUE,
	last_name VARCHAR(100) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	middle_name VARCHAR(100),
	extension_name VARCHAR(20),
	middle_initial VARCHAR(2),
	birthday DATE,
	sex ENUM('Male', 'Female', 'Other'),
	cp_no VARCHAR(20),
	spoken_language VARCHAR(100),
	fb_name VARCHAR(100),
	esc_id_no VARCHAR(50),
	former_school VARCHAR(150),
	father_name VARCHAR(150),
	mother_name VARCHAR(150),
	contact_person VARCHAR(150),
	contact_fb_name VARCHAR(100),
	street_name VARCHAR(150),
	barangay VARCHAR(100),
	municipality VARCHAR(100),
	province VARCHAR(100),
	contact_cp_no VARCHAR(20),
	student_status ENUM('active', 'enrolled', 'inactive', 'transferred', 'graduated', 'archived', 'deceased') DEFAULT 'active',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

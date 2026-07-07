CREATE TABLE IF NOT EXISTS courses (
    courseId CHAR(36) NOT NULL,
    teacherId CHAR(36) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    academicLevel VARCHAR(100),
    enrollmentCode VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (courseId),
    UNIQUE KEY uq_courses_enrollmentCode (enrollmentCode),
    KEY idx_courses_teacherId (teacherId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS enrollments (
    enrollmentId CHAR(36) NOT NULL,
    userId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    roleInCourse ENUM('student', 'teacher') NOT NULL DEFAULT 'student',
    createdAt DATETIME NOT NULL,
    PRIMARY KEY (enrollmentId),
    UNIQUE KEY uq_enrollments_user_course (userId, courseId),
    KEY idx_enrollments_courseId (courseId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS quests (
    questId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    link VARCHAR(255),
    XPValue INT NOT NULL DEFAULT 0,
    deadline DATETIME,
    questType ENUM('individual', 'group', 'external', 'offline') NOT NULL DEFAULT 'individual',
    isCompulsory TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (questId),
    KEY idx_quests_courseId (courseId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS badges (
    badgeId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    iconPath VARCHAR(255),
    criteriaType ENUM('xp', 'manual') NOT NULL DEFAULT 'xp',
    threshold INT NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (badgeId),
    KEY idx_badges_courseId (courseId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS student_badges (
    studentBadgeId CHAR(36) NOT NULL,
    badgeId CHAR(36) NOT NULL,
    studentId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    awardedAt DATETIME NOT NULL,
    PRIMARY KEY (studentBadgeId),
    UNIQUE KEY uq_student_badges (badgeId, studentId),
    KEY idx_student_badges_course_student (courseId, studentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS results (
    resultId CHAR(36) NOT NULL,
    questId CHAR(36) NOT NULL,
    studentId CHAR(36) NOT NULL,
    score INT DEFAULT 0,
    completionStatus ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
    awardedXP INT NOT NULL DEFAULT 0,
    evidenceLink VARCHAR(255),
    evidenceFile VARCHAR(255),
    rubricRating INT DEFAULT NULL,
    teacherComment TEXT,
    submissionTime DATETIME NOT NULL,
    PRIMARY KEY (resultId),
    UNIQUE KEY uq_results_quest_student (questId, studentId),
    KEY idx_results_studentId (studentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS xp_records (
    studentId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    totalXP INT NOT NULL DEFAULT 0,
    level INT NOT NULL DEFAULT 1,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (studentId, courseId),
    KEY idx_xp_records_courseId (courseId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS milestones (
    milestoneId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    requiredXP INT NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL,
    PRIMARY KEY (milestoneId),
    KEY idx_milestones_courseId (courseId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS announcements (
    announcementId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT,
    createdBy CHAR(36) NOT NULL,
    createdAt DATETIME NOT NULL,
    PRIMARY KEY (announcementId),
    KEY idx_announcements_courseId (courseId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS reflections (
    reflectionId CHAR(36) NOT NULL,
    questId CHAR(36) NOT NULL,
    studentId CHAR(36) NOT NULL,
    text TEXT NOT NULL,
    teacherComment TEXT,
    timestamp DATETIME NOT NULL,
    PRIMARY KEY (reflectionId),
    KEY idx_reflections_quest_student (questId, studentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS notifications (
    notificationId CHAR(36) NOT NULL,
    userId CHAR(36) NOT NULL,
    courseId CHAR(36),
    notificationType VARCHAR(30) NOT NULL DEFAULT 'general',
    title VARCHAR(150) NOT NULL,
    message TEXT,
    isRead TINYINT(1) NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL,
    PRIMARY KEY (notificationId),
    KEY idx_notifications_userId (userId),
    KEY idx_notifications_type (notificationType)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    messageId CHAR(36) NOT NULL,
    senderId CHAR(36) NOT NULL,
    receiverId CHAR(36) NOT NULL,
    message TEXT NOT NULL,
    isRead TINYINT(1) NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL,
    PRIMARY KEY (messageId),
    KEY idx_chat_sender_receiver (senderId, receiverId),
    KEY idx_chat_receiver_read (receiverId, isRead),
    KEY idx_chat_createdAt (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS reward_items (
    itemId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    teacherId CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    imagePath VARCHAR(255),
    xpCost INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    status ENUM('pending', 'approved', 'delisted', 'archived') NOT NULL DEFAULT 'pending',
    adminId CHAR(36) DEFAULT NULL,
    adminNote TEXT,
    reviewedAt DATETIME DEFAULT NULL,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (itemId),
    KEY idx_reward_items_course (courseId),
    KEY idx_reward_items_teacher (teacherId),
    KEY idx_reward_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS reward_redemptions (
    redemptionId CHAR(36) NOT NULL,
    itemId CHAR(36) NOT NULL,
    courseId CHAR(36) NOT NULL,
    studentId CHAR(36) NOT NULL,
    xpCost INT NOT NULL DEFAULT 0,
    status ENUM('pending', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    teacherNote TEXT,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME NOT NULL,
    fulfilledAt DATETIME DEFAULT NULL,
    PRIMARY KEY (redemptionId),
    KEY idx_reward_redemptions_item (itemId),
    KEY idx_reward_redemptions_course_student (courseId, studentId),
    KEY idx_reward_redemptions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    settingKey VARCHAR(100) NOT NULL,
    settingValue TEXT,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (settingKey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

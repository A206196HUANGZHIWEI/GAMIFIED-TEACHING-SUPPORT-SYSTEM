CREATE TABLE IF NOT EXISTS system_settings (
    settingKey VARCHAR(100) NOT NULL,
    settingValue TEXT,
    updatedAt DATETIME NOT NULL,
    PRIMARY KEY (settingKey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- User profile fields for avatar, display title, and equipped badge.
ALTER TABLE users ADD COLUMN avatarPath VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN profileTitle VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN equippedBadgeId CHAR(36) DEFAULT NULL;

-- Notification category for personal notification filters.
ALTER TABLE notifications ADD COLUMN notificationType VARCHAR(30) NOT NULL DEFAULT 'general' AFTER courseId;
CREATE INDEX idx_notifications_type ON notifications (notificationType);

-- Direct chat messages between any registered users.
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

-- XP store items created by teachers and reviewed by administrators.
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

-- Student XP redemptions. Fulfilment is performed offline by the teacher.
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

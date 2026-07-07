<article class="list-item notification-item <?php echo (int) $notice['isRead'] === 0 ? 'unread' : ''; ?>">
    <div class="section-head">
        <div class="notification-copy">
            <div class="notification-meta">
                <span class="notification-type type-<?php echo h($notice['resolvedType']); ?>"><?php echo h($notice['resolvedTypeLabel']); ?></span>
                <?php if ((int) $notice['isRead'] === 0): ?>
                    <span class="notification-dot">Unread</span>
                <?php endif; ?>
            </div>
            <strong><?php echo h($notice['title']); ?></strong>
            <span><?php echo h($notice['message']); ?></span>
            <span class="muted">
                <?php echo h($notice['createdAt']); ?>
                <?php if ($notice['courseTitle']): ?>
                    | <?php echo h($notice['courseTitle']); ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="actions compact-actions">
            <?php if ($notice['courseId']): ?>
                <a class="button secondary" href="course.php?courseId=<?php echo h($notice['courseId']); ?>">Open</a>
            <?php endif; ?>
            <?php if ((int) $notice['isRead'] === 0): ?>
                <form method="post" action="<?php echo h(notification_filter_url($status, $type)); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="mark_one">
                    <input type="hidden" name="status" value="<?php echo h($status); ?>">
                    <input type="hidden" name="type" value="<?php echo h($type); ?>">
                    <input type="hidden" name="notificationId" value="<?php echo h($notice['notificationId']); ?>">
                    <button type="submit">Read</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>

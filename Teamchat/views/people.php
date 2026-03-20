<div id="people_view" style="display: none; flex-direction: column; height: 100%; width: 100%; background: var(--surface);">
    <div style="padding: 32px 40px; border-bottom: 1px solid var(--border); background: var(--surface); position: sticky; top: 0; z-index: 10;">
        <h2 style="font-size: 2rem; font-weight:800; color: var(--text-dark);">People Directory</h2>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 8px;">Find and connect with everyone in your organization.</p>
    </div>
    <div style="overflow-y: auto; flex: 1; padding: 20px 0;">
        <?php foreach($all_users as $u): if($u['id'] != $my_id): ?>
            <div class="people-card">
                <div class="people-info">
                    <img src="<?= $u['profile_img'] ?>" class="avatar" loading="lazy" style="width: 56px; height: 56px;">
                    <div>
                        <div style="font-weight:700; font-size: 1.1rem; color: var(--text-dark); margin-bottom: 4px;"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size:0.9rem; color:var(--text-muted); font-weight: 500;"><i class="ri-briefcase-4-line" style="vertical-align: middle;"></i> <?= $u['role'] ?> <?= !empty($u['department']) ? '&bull; ' . $u['department'] : '' ?></div>
                    </div>
                </div>
                <button class="people-btn" onclick="startChat(<?= $u['id'] ?>)" title="Message <?= htmlspecialchars($u['name']) ?>">
                    <i class="ri-chat-3-fill"></i>
                </button>
            </div>
        <?php endif; endforeach; ?>
    </div>
</div>
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

function render_memos_sidebar(?array $user, array $queryParams): void
{
    $activeRoute = route_name();
    $currentKeyword = (string) ($queryParams['keyword'] ?? '');
    $selectedTagId = (int) ($queryParams['tag_id'] ?? 0);
    $selectedDate = (string) ($queryParams['date_from'] ?? '');
    if ($selectedDate !== '' && $selectedDate !== (string) ($queryParams['date_to'] ?? '')) {
        $selectedDate = ''; // 不是单日筛选
    }

    // 计算月历逻辑
    $calYm = (string) ($_GET['cal_ym'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $calYm)) {
        $calYm = date('Y-m');
    }
    $calTime = strtotime($calYm . '-01');
    $prevYm = date('Y-m', strtotime('-1 month', $calTime));
    $nextYm = date('Y-m', strtotime('+1 month', $calTime));
    $currentYearMonthText = date('Y年n月', $calTime);

    $scope = $activeRoute === 'my-posts' ? 'mine' : 'all';
    $activeDays = get_active_post_dates($calYm, $scope, (int) ($user['id'] ?? 0));
    $tags = list_tags_with_counts(true);

    // 计算日历网格
    $firstDayOfWeek = (int) date('w', $calTime); // 0 (Sunday) to 6 (Saturday)
    $daysInMonth = (int) date('t', $calTime);
    $todayStr = today();
    ?>
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">M</div>
            <div class="sidebar-title"><?= h(app_config('app_name')) ?></div>
        </div>

        <!-- 搜索框 -->
        <form method="get" action="/" class="search-form">
            <input type="hidden" name="route" value="<?= h($activeRoute === 'recycle-bin' ? 'recycle-bin' : ($activeRoute === 'my-posts' ? 'my-posts' : 'posts')) ?>">
            <span class="search-icon">🔍</span>
            <input type="text" name="keyword" class="search-input" value="<?= h($currentKeyword) ?>" placeholder="搜索订货记录...">
        </form>

        <!-- 月历组件 (Memos Calendar) -->
        <div class="calendar-card">
            <div class="calendar-header">
                <span><?= h($currentYearMonthText) ?></span>
                <div>
                    <a href="/?route=<?= h($activeRoute) ?>&cal_ym=<?= h($prevYm) ?>" class="calendar-nav-btn">&lt;</a>
                    <a href="/?route=<?= h($activeRoute) ?>&cal_ym=<?= h($nextYm) ?>" class="calendar-nav-btn">&gt;</a>
                </div>
            </div>
            <div class="calendar-grid">
                <div class="calendar-weekday">日</div>
                <div class="calendar-weekday">一</div>
                <div class="calendar-weekday">二</div>
                <div class="calendar-weekday">三</div>
                <div class="calendar-weekday">四</div>
                <div class="calendar-weekday">五</div>
                <div class="calendar-weekday">六</div>

                <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                    <div class="calendar-day other-month"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $dayStr = sprintf('%s-%02d', $calYm, $day);
                    $hasPost = !empty($activeDays[$dayStr]);
                    $isSelected = ($selectedDate === $dayStr);
                    $isToday = ($dayStr === $todayStr);

                    $targetUrl = $isSelected
                        ? '/?route=' . h($activeRoute) // 点击已选中的日期取消筛选
                        : '/?route=' . h($activeRoute) . '&date_from=' . $dayStr . '&date_to=' . $dayStr;
                    ?>
                    <a href="<?= $targetUrl ?>"
                       class="calendar-day <?= $hasPost ? 'has-post' : '' ?> <?= $isSelected ? 'is-selected' : '' ?> <?= $isToday ? 'is-today' : '' ?>"
                       title="<?= $dayStr ?><?= $hasPost ? ' (' . $activeDays[$dayStr] . '条记录)' : '' ?>">
                        <?= $day ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- 导航菜单 -->
        <nav class="sidebar-menu">
            <a class="nav-item <?= $activeRoute === 'posts' ? 'active' : '' ?>" href="/?route=posts">
                <span class="icon">📋</span> 全部记录
            </a>
            <a class="nav-item <?= $activeRoute === 'my-posts' ? 'active' : '' ?>" href="/?route=my-posts">
                <span class="icon">👤</span> 我的记录
            </a>
            <a class="nav-item <?= $activeRoute === 'comments-today' ? 'active' : '' ?>" href="/?route=comments-today">
                <span class="icon">✅</span> 今日待办注释
            </a>
            <?php if (is_admin()): ?>
                <a class="nav-item <?= $activeRoute === 'recycle-bin' ? 'active' : '' ?>" href="/?route=recycle-bin">
                    <span class="icon">🗑️</span> 回收站
                </a>
            <?php endif; ?>
        </nav>

        <!-- 标签区 -->
        <div>
            <div class="sidebar-section-title">
                <span>标签分类</span>
            </div>
            <div class="tag-tree-list">
                <a href="/?route=<?= h($activeRoute) ?>" class="tag-tree-item <?= $selectedTagId === 0 ? 'active' : '' ?>">
                    <span># 全部标签</span>
                </a>
                <?php foreach ($tags as $tag): ?>
                    <a href="/?route=<?= h($activeRoute) ?>&tag_id=<?= (int) $tag['id'] ?>" class="tag-tree-item <?= $selectedTagId === (int) $tag['id'] ? 'active' : '' ?>">
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= h($tag['color']) ?>;"></span>
                            <?= h($tag['name']) ?>
                        </span>
                        <?php if ((int) $tag['post_count'] > 0): ?>
                            <span class="tag-badge"><?= (int) $tag['post_count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 底部用户信息（点击进入设置） -->
        <?php if ($user): ?>
            <div class="sidebar-user">
                <a href="/?route=settings" class="user-info-btn <?= $activeRoute === 'settings' ? 'active' : '' ?>" title="点击进入系统与个人设置">
                    <div class="user-avatar"><?= mb_substr($user['display_name'], 0, 1, 'UTF-8') ?></div>
                    <div class="user-meta-box">
                        <div class="user-name"><?= h($user['display_name']) ?> <span class="settings-badge-icon">⚙️</span></div>
                        <div class="user-role"><?= $user['role'] === 'admin' ? '系统管理员' : '订货员' ?></div>
                    </div>
                </a>
                <form method="post" action="/?route=logout">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <button class="logout-btn" type="submit" title="退出登录">🚪</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>
    <?php
}

function render_header(string $title, ?array $user = null): void
{
    ?>
    <!doctype html>
    <html lang="zh-CN">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h(page_title($title)) ?></title>
        <link rel="stylesheet" href="/assets/app.css">
    </head>
    <body>
    <div class="app-layout">
    <?php
    if ($user) {
        render_memos_sidebar($user, $_GET);
    }
    ?>
    <main class="app-main">
        <?php foreach (['success', 'error'] as $flashType): ?>
            <?php if ($message = flash($flashType)): ?>
                <div class="alert <?= $flashType === 'success' ? 'alert-success' : 'alert-error' ?>">
                    <?= $flashType === 'success' ? '✅' : '⚠️' ?> <?= h($message) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    </div>
    <script>
        // 初始化剪贴板与文件上传实时缩略图预览
        function initPasteAndUpload(config) {
            const textarea = document.getElementById(config.textareaId);
            const fileInput = document.getElementById(config.fileInputId);
            const previewBox = document.getElementById(config.previewBoxId);
            const statusNode = document.getElementById(config.statusId);
            if (!textarea || !fileInput) return;

            let fileList = [];

            function syncToFileTransfer() {
                if (typeof DataTransfer === 'undefined') return;
                const dt = new DataTransfer();
                fileList.forEach(function (f) { dt.items.add(f); });
                fileInput.files = dt.files;
            }

            function updatePreview() {
                if (!previewBox) return;
                previewBox.innerHTML = '';
                if (fileList.length === 0) {
                    previewBox.style.display = 'none';
                    if (statusNode) statusNode.textContent = '';
                    return;
                }

                previewBox.style.display = 'flex';
                if (statusNode) statusNode.textContent = '📎 待上传 ' + fileList.length + ' 张图片';

                fileList.forEach(function (file, index) {
                    const item = document.createElement('div');
                    item.className = 'memo-preview-item';

                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.onload = function() { URL.revokeObjectURL(this.src); };

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'memo-preview-remove';
                    removeBtn.innerHTML = '✕';
                    removeBtn.title = '删除此图片';
                    removeBtn.onclick = function (e) {
                        e.preventDefault();
                        fileList.splice(index, 1);
                        syncToFileTransfer();
                        updatePreview();
                    };

                    item.appendChild(img);
                    item.appendChild(removeBtn);
                    previewBox.appendChild(item);
                });
            }

            // 监听文件 input change
            fileInput.addEventListener('change', function () {
                Array.from(this.files || []).forEach(function (f) {
                    if (f.type.indexOf('image/') === 0) {
                        fileList.push(f);
                    }
                });
                syncToFileTransfer();
                updatePreview();
            });

            // 监听 textarea 的 paste 事件
            textarea.addEventListener('paste', function (e) {
                if (!e.clipboardData) return;
                const items = Array.from(e.clipboardData.items || []);
                const files = Array.from(e.clipboardData.files || []);

                let added = false;

                // 优先从 files 读取
                files.forEach(function (f) {
                    if (f.type && f.type.indexOf('image/') === 0) {
                        fileList.push(f);
                        added = true;
                    }
                });

                // 从 items (截图二进制数据流) 读取
                if (!added) {
                    items.forEach(function (item, idx) {
                        if (item.type && item.type.indexOf('image/') === 0) {
                            const blob = item.getAsFile();
                            if (blob) {
                                const ext = (blob.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                                const newFile = new File([blob], 'paste-' + Date.now() + '-' + idx + '.' + ext, { type: blob.type });
                                fileList.push(newFile);
                                added = true;
                            }
                        }
                    });
                }

                if (added) {
                    syncToFileTransfer();
                    updatePreview();
                }
            });
        }

        // 初始化主页发帖框粘贴与预览
        initPasteAndUpload({
            textareaId: 'quick-content-input',
            fileInputId: 'quick-image-input',
            previewBoxId: 'quick-image-preview-box',
            statusId: 'quick-image-status'
        });

        // 初始化编辑页面粘贴与预览
        initPasteAndUpload({
            textareaId: 'edit-content-input',
            fileInputId: 'edit-images-input',
            previewBoxId: 'edit-image-preview-box',
            statusId: 'edit-images-input-status'
        });

        // 标签下拉框切换 (创建框)
        function toggleTagPopover(btn) {
            const container = btn.closest('.tag-select-container');
            if (container) {
                container.classList.toggle('open');
            }
        }

        // 快捷打标签异步无刷新切换
        async function togglePostTagAsync(postId, tagId) {
            const formData = new FormData();
            formData.append('_token', '<?= h(csrf_token()) ?>');
            formData.append('post_id', postId);
            formData.append('tag_id', tagId);

            try {
                const response = await fetch('/?route=post-tag-toggle', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    const card = document.getElementById('memo-card-' + postId);
                    if (!card) return;
                    const chipsContainer = card.querySelector('.reaction-chips-container');
                    const pickerMenu = card.querySelector('.reaction-picker-menu');

                    // 更新底部展示的胶囊
                    if (chipsContainer) {
                        chipsContainer.innerHTML = '';
                        (data.tags || []).forEach(function (tag) {
                            const chip = document.createElement('span');
                            chip.className = 'reaction-chip active';
                            chip.style.background = tag.color + '18';
                            chip.style.color = tag.color;
                            chip.style.borderColor = tag.color + '55';
                            chip.title = '点击移除此标签';
                            chip.onclick = function() { togglePostTagAsync(postId, tag.id); };
                            chip.innerHTML = '<span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: ' + tag.color + ';"></span> ' + tag.name;
                            chipsContainer.appendChild(chip);
                        });
                    }

                    // 更新下拉菜单里的对勾与高亮状态
                    if (pickerMenu) {
                        const currentTagIds = (data.tags || []).map(function(t) { return parseInt(t.id, 10); });
                        pickerMenu.querySelectorAll('.reaction-menu-item').forEach(function(item) {
                            const tId = parseInt(item.getAttribute('data-tag-id'), 10);
                            const checkSpan = item.querySelector('.tag-check-mark');
                            if (currentTagIds.indexOf(tId) !== -1) {
                                item.classList.add('tagged');
                                if (!checkSpan) {
                                    const span = document.createElement('span');
                                    span.className = 'tag-check-mark';
                                    span.textContent = '✓';
                                    item.appendChild(span);
                                }
                            } else {
                                item.classList.remove('tagged');
                                if (checkSpan) {
                                    checkSpan.remove();
                                }
                            }
                        });
                    }
                }
            } catch (err) {
                console.error('标签切换失败', err);
            }
        }

        // 展开/收起卡片内快速添加注释框
        function toggleQuickCommentForm(postId) {
            const form = document.getElementById('quick-comment-form-' + postId);
            if (!form) return;
            form.classList.toggle('open');
            if (form.classList.contains('open')) {
                const input = document.getElementById('quick-comment-input-' + postId);
                if (input) input.focus();
            }
        }

        // 提交单行注释
        async function submitQuickComment(e, postId) {
            e.preventDefault();
            const input = document.getElementById('quick-comment-input-' + postId);
            if (!input) return;
            const content = input.value.trim();
            if (!content) return;

            const formData = new FormData();
            formData.append('_token', '<?= h(csrf_token()) ?>');
            formData.append('post_id', postId);
            formData.append('content', content);

            try {
                const response = await fetch('/?route=comment-add', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await response.json();
                if (data.success && data.comment) {
                    input.value = '';
                    const list = document.getElementById('card-comment-list-' + postId);
                    if (list) {
                        list.style.display = 'flex';
                        const row = document.createElement('div');
                        row.className = 'card-comment-row';
                        row.id = 'comment-row-' + data.comment.id;
                        row.innerHTML = `
                            <input type="checkbox" class="card-comment-checkbox" onchange="toggleCommentStatusAsync(${data.comment.id})">
                            <span class="card-comment-text">${data.comment.content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                            <span class="card-comment-meta">${data.comment.user_name} · ${data.comment.created_at}</span>
                        `;
                        list.appendChild(row);
                    }
                    // 更新卡片右上角评论数
                    const card = document.getElementById('memo-card-' + postId);
                    if (card) {
                        const countLink = card.querySelector('.memo-header-ops a[title="详情"]');
                        if (countLink && data.comments) {
                            countLink.textContent = '💬 ' + data.comments.length;
                        }
                    }
                } else if (data.message) {
                    alert(data.message);
                }
            } catch (err) {
                console.error('添加注释失败', err);
            }
        }

        // 切换注释完成状态
        async function toggleCommentStatusAsync(commentId) {
            const formData = new FormData();
            formData.append('_token', '<?= h(csrf_token()) ?>');
            formData.append('comment_id', commentId);

            try {
                const response = await fetch('/?route=comment-toggle', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    const row = document.getElementById('comment-row-' + commentId);
                    if (row) {
                        if (data.is_done === 1) {
                            row.classList.add('is-done');
                        } else {
                            row.classList.remove('is-done');
                        }
                    }
                }
            } catch (err) {
                console.error('切换注释状态失败', err);
            }
        }

        // 切换弹出菜单
        function toggleReactionMenu(btn) {
            const container = btn.closest('.reaction-popover-container');
            if (container) {
                const isOpen = container.classList.contains('open');
                document.querySelectorAll('.reaction-popover-container.open').forEach(function(el) {
                    el.classList.remove('open');
                });
                if (!isOpen) {
                    container.classList.add('open');
                }
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.tag-select-container')) {
                document.querySelectorAll('.tag-select-container.open').forEach(function(el) {
                    el.classList.remove('open');
                });
            }
            if (!e.target.closest('.reaction-popover-container')) {
                document.querySelectorAll('.reaction-popover-container.open').forEach(function(el) {
                    el.classList.remove('open');
                });
            }
        });
    </script>
    </body>
    </html>
    <?php
}

function render_post_card(array $post, array $user): void
{
    $canEdit = can_edit_post($post, $user);
    static $allTagsCache = null;
    if ($allTagsCache === null) {
        $allTagsCache = list_tags(true);
    }
    $postTagIds = array_map(static function ($t) { return (int) $t['id']; }, $post['tags'] ?? []);
    ?>
    <article class="memo-card" id="memo-card-<?= (int) $post['id'] ?>">
        <div class="memo-card-header">
            <div class="memo-time-meta">
                <span class="author-tag"><?= h($post['author_name']) ?></span>
                <span>·</span>
                <span><?= h(date('m月d日 H:i', strtotime($post['created_at']))) ?></span>
                <span class="memo-ship-badge">📅 期望发货: <?= h($post['expected_ship_date']) ?></span>
            </div>
            <div class="memo-header-ops">
                <a class="memo-op-btn" href="/?route=post-view&id=<?= (int) $post['id'] ?>" title="详情">💬 <?= (int) ($post['comment_count'] ?? 0) ?></a>
                <?php if ($canEdit): ?>
                    <a class="memo-op-btn" href="/?route=post-edit&id=<?= (int) $post['id'] ?>" title="编辑">✏️</a>
                    <form method="post" action="/?route=post-delete" style="display:inline;" onsubmit="return confirm('确认将该记录移入回收站吗？');">
                        <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                        <button class="memo-op-btn" type="submit" title="删除" style="color: var(--danger);">🗑️</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="memo-content"><?= nl2br(h($post['content'])) ?></div>

        <?php if (!empty($post['images'])): ?>
            <div class="memo-attachments-box">
                <div class="memo-attachments-title">📎 附件图片 (<?= count($post['images']) ?>)</div>
                <div class="memo-image-gallery">
                    <?php foreach ($post['images'] as $image): ?>
                        <a class="memo-image-item" href="<?= h(image_url($image['file_path'])) ?>" target="_blank" rel="noreferrer" title="<?= h($image['original_name']) ?>">
                            <img src="<?= h(image_url($image['file_path'])) ?>" alt="<?= h($image['original_name']) ?>" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="memo-card-footer">
            <!-- 像打表情一样的快捷打标签区域 -->
            <div class="memo-tags-reactions" data-post-id="<?= (int) $post['id'] ?>">
                <div class="reaction-chips-container" style="display: inline-flex; flex-wrap: wrap; gap: 6px;">
                    <?php if (!empty($post['tags'])): ?>
                        <?php foreach ($post['tags'] as $tag): ?>
                            <span class="reaction-chip active"
                                  style="background: <?= h($tag['color']) ?>18; color: <?= h($tag['color']) ?>; border-color: <?= h($tag['color']) ?>55;"
                                  onclick="togglePostTagAsync(<?= (int) $post['id'] ?>, <?= (int) $tag['id'] ?>)"
                                  title="点击移除此标签">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: <?= h($tag['color']) ?>;"></span>
                                <?= h($tag['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 类似表情的快捷选择菜单按钮 -->
                <div class="reaction-popover-container">
                    <button type="button" class="add-reaction-btn" onclick="toggleReactionMenu(this)" title="快捷打标签/分类">
                        <span>🏷️</span>
                    </button>
                    <div class="reaction-picker-menu">
                        <?php foreach ($allTagsCache as $availableTag): ?>
                            <?php $isTagged = in_array((int) $availableTag['id'], $postTagIds, true); ?>
                            <button type="button"
                                    class="reaction-menu-item <?= $isTagged ? 'tagged' : '' ?>"
                                    data-tag-id="<?= (int) $availableTag['id'] ?>"
                                    onclick="togglePostTagAsync(<?= (int) $post['id'] ?>, <?= (int) $availableTag['id'] ?>)">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= h($availableTag['color']) ?>;"></span>
                                <span><?= h($availableTag['name']) ?></span>
                                <?php if ($isTagged): ?><span class="tag-check-mark">✓</span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="memo-interactive-tools">
                <button type="button" class="comment-btn-link" onclick="toggleQuickCommentForm(<?= (int) $post['id'] ?>)" style="background:none;border:none;cursor:pointer;">
                    <span>✍️ 展开/添加注释</span>
                </button>
            </div>
        </div>

        <!-- 注释显示在标签下面，一行显示一条注释 -->
        <?php
        $comments = $post['comments'] ?? [];
        ?>
        <div class="card-comment-list" id="card-comment-list-<?= (int) $post['id'] ?>" style="<?= empty($comments) ? 'display:none;' : '' ?>">
            <?php foreach ($comments as $cmt): ?>
                <div class="card-comment-row <?= (int) $cmt['is_done'] === 1 ? 'is-done' : '' ?>" id="comment-row-<?= (int) $cmt['id'] ?>">
                    <input type="checkbox"
                           class="card-comment-checkbox"
                           <?= (int) $cmt['is_done'] === 1 ? 'checked' : '' ?>
                           onchange="toggleCommentStatusAsync(<?= (int) $cmt['id'] ?>)">
                    <span class="card-comment-text"><?= h($cmt['content']) ?></span>
                    <span class="card-comment-meta"><?= h($cmt['user_name']) ?> · <?= h(date('m-d H:i', strtotime($cmt['created_at']))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 快速添加单行注释输入框 -->
        <form class="card-quick-comment-form" id="quick-comment-form-<?= (int) $post['id'] ?>" onsubmit="submitQuickComment(event, <?= (int) $post['id'] ?>)">
            <input type="text"
                   class="quick-comment-input"
                   id="quick-comment-input-<?= (int) $post['id'] ?>"
                   placeholder="写下单行注释，按回车添加..."
                   autocomplete="off">
            <button type="submit" class="btn-primary" style="padding: 5px 12px; font-size: 12.5px; white-space: nowrap;">发表</button>
        </form>
    </article>
    <?php
}

if (!file_exists(app_config('db_path'))) {
    render_header('初始化');
    ?>
    <div class="panel-card empty-placeholder">
        <h2>数据库尚未初始化</h2>
        <p style="margin: 10px 0;">请先执行数据库初始化脚本：</p>
        <pre style="background: #e2e8f0; padding: 8px 12px; border-radius: 6px; display: inline-block;">php scripts/init_db.php</pre>
        <p style="margin-top: 10px;">默认管理员账号：<code>admin</code>，默认密码：<code>admin123</code></p>
    </div>
    <?php
    render_footer();
    exit;
}

$route = route_name();

if ($route === 'login' && is_post_request()) {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = find_user_by_username($username);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('error', '账号或密码错误');
        redirect('/?route=login');
    }

    login_user($user);
    flash('success', '登录成功');
    redirect('/?route=posts');
}

if ($route === 'logout' && is_post_request()) {
    verify_csrf();
    logout_user();
    flash('success', '已退出登录');
    redirect('/?route=login');
}

$user = current_user();

if (!$user && $route !== 'login') {
    redirect('/?route=login');
}

if ($route === 'user-save' && is_post_request()) {
    require_admin();
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'user');

    if ($username === '' || $displayName === '' || $password === '') {
        flash('error', '用户名、显示名、密码不能为空');
        redirect('/?route=settings&tab=users');
    }

    if (find_user_by_username($username)) {
        flash('error', '用户名已存在');
        redirect('/?route=settings&tab=users');
    }

    create_user($username, $password, $displayName, $role);
    flash('success', '用户创建成功');
    redirect('/?route=settings&tab=users');
}

if ($route === 'tag-save' && is_post_request()) {
    require_admin();
    verify_csrf();
    $tagId = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $color = trim((string) ($_POST['color'] ?? '#2563eb'));
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        flash('error', '标签名称不能为空');
        redirect('/?route=settings&tab=tags');
    }

    if ($tagId > 0) {
        update_tag($tagId, $name, $color, $sortOrder, $isActive);
        flash('success', '标签更新成功');
    } else {
        create_tag($name, $color, $sortOrder);
        flash('success', '标签创建成功');
    }

    redirect('/?route=settings&tab=tags');
}

if ($route === 'webhook-save' && is_post_request()) {
    require_admin();
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $targetUrl = trim((string) ($_POST['target_url'] ?? ''));
    $secret = trim((string) ($_POST['secret'] ?? ''));

    if ($name === '' || $targetUrl === '' || $secret === '') {
        flash('error', 'Webhook 名称、地址、密钥不能为空');
        redirect('/?route=settings&tab=webhooks');
    }

    create_webhook($name, $targetUrl, $secret);
    flash('success', 'Webhook 已添加');
    redirect('/?route=settings&tab=webhooks');
}

if ($route === 'webhook-toggle' && is_post_request()) {
    require_admin();
    verify_csrf();
    toggle_webhook((int) ($_POST['id'] ?? 0));
    flash('success', 'Webhook 状态已切换');
    redirect('/?route=settings&tab=webhooks');
}

if ($route === 'profile-save' && is_post_request()) {
    $user = require_login();
    verify_csrf();
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($displayName === '') {
        flash('error', '显示姓名不能为空');
        redirect('/?route=settings&tab=profile');
    }

    update_user_profile((int) $user['id'], $displayName, $password !== '' ? $password : null);
    flash('success', '个人资料已更新');
    redirect('/?route=settings&tab=profile');
}

if ($route === 'post-tag-toggle' && is_post_request()) {
    $user = require_login();
    verify_csrf();
    $postId = (int) ($_POST['post_id'] ?? 0);
    $tagId = (int) ($_POST['tag_id'] ?? 0);

    $isTagged = toggle_post_tag($postId, $tagId, (int) $user['id']);
    dispatch_post_webhook('post.updated', $postId);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_tagged' => $isTagged,
            'tags' => get_post_tags($postId),
        ]);
        exit;
    }

    flash('success', '标签已更新');
    redirect($_SERVER['HTTP_REFERER'] ?? '/?route=posts');
}

if ($route === 'post-store' && is_post_request()) {
    $user = require_login();
    verify_csrf();

    $content = trim((string) ($_POST['content'] ?? ''));
    $expectedShipDate = (string) ($_POST['expected_ship_date'] ?? today());
    $tagIds = array_map('intval', $_POST['tag_ids'] ?? []);

    if ($content === '') {
        flash('error', '订货内容不能为空');
        redirect('/?route=posts');
    }

    try {
        $images = store_uploaded_images($_FILES['images'] ?? []);
        $postId = create_post((int) $user['id'], $content, $expectedShipDate, $images);
        replace_post_tags($postId, $tagIds, (int) $user['id']);
        dispatch_post_webhook('post.created', $postId);
        flash('success', '订货记录已发布');
        redirect('/?route=posts');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/?route=posts');
    }
}

if ($route === 'post-update' && is_post_request()) {
    $user = require_login();
    verify_csrf();

    $postId = (int) ($_POST['id'] ?? 0);
    $post = find_post_by_id($postId);
    if (!$post || !can_edit_post($post, $user)) {
        flash('error', '无权编辑该订货记录');
        redirect('/?route=posts');
    }

    $content = trim((string) ($_POST['content'] ?? ''));
    $expectedShipDate = (string) ($_POST['expected_ship_date'] ?? today());
    $removeImageIds = array_map('intval', $_POST['remove_image_ids'] ?? []);

    if ($content === '') {
        flash('error', '订货内容不能为空');
        redirect('/?route=post-edit&id=' . $postId);
    }

    try {
        $newImages = store_uploaded_images($_FILES['images'] ?? []);
        update_post_record($postId, $content, $expectedShipDate, $newImages, $removeImageIds);
        dispatch_post_webhook('post.updated', $postId);
        flash('success', '订货记录已更新');
        redirect('/?route=post-view&id=' . $postId);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/?route=post-edit&id=' . $postId);
    }
}

if ($route === 'post-delete' && is_post_request()) {
    $user = require_login();
    verify_csrf();

    $postId = (int) ($_POST['id'] ?? 0);
    $post = find_post_by_id($postId);
    if (!$post || !can_edit_post($post, $user)) {
        flash('error', '无权删除该订货记录');
        redirect('/?route=posts');
    }

    soft_delete_post($postId, (int) $user['id']);
    dispatch_post_webhook('post.deleted', $postId);
    flash('success', '订货记录已移入回收站');
    redirect('/?route=posts');
}

if ($route === 'post-restore' && is_post_request()) {
    require_admin();
    verify_csrf();
    restore_post((int) ($_POST['id'] ?? 0));
    flash('success', '订货记录已恢复');
    redirect('/?route=recycle-bin');
}

if ($route === 'post-tags' && is_post_request()) {
    $user = require_login();
    verify_csrf();
    $postId = (int) ($_POST['post_id'] ?? 0);
    $post = find_post_by_id($postId);
    if (!$post) {
        flash('error', '记录不存在');
        redirect('/?route=posts');
    }

    $tagIds = array_map('intval', $_POST['tag_ids'] ?? []);
    replace_post_tags($postId, $tagIds, (int) $user['id']);
    dispatch_post_webhook('post.updated', $postId);
    flash('success', '标签已更新');
    redirect('/?route=post-view&id=' . $postId);
}

if ($route === 'comment-add' && is_post_request()) {
    $user = require_login();
    verify_csrf();

    $postId = (int) ($_POST['post_id'] ?? 0);
    $content = trim((string) ($_POST['content'] ?? ''));
    $returnRoute = (string) ($_POST['return_route'] ?? 'posts');
    $post = find_post_by_id($postId);

    if (!$post) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '订货记录不存在']);
            exit;
        }
        flash('error', '订货记录不存在');
        redirect('/?route=posts');
    }

    if ($content === '') {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '注释内容不能为空']);
            exit;
        }
        flash('error', '注释内容不能为空');
        redirect('/?route=' . $returnRoute);
    }

    $commentId = create_comment($postId, (int) $user['id'], $content);
    dispatch_post_webhook('post.updated', $postId);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $commentId,
                'post_id' => $postId,
                'content' => $content,
                'user_name' => $user['display_name'],
                'is_done' => 0,
                'created_at' => date('m-d H:i')
            ],
            'comments' => get_post_comments($postId),
        ]);
        exit;
    }

    flash('success', '注释已添加');
    if ($returnRoute === 'post-view') {
        redirect('/?route=post-view&id=' . $postId);
    }
    redirect('/?route=' . $returnRoute);
}

if ($route === 'comment-toggle' && is_post_request()) {
    $user = require_login();
    verify_csrf();

    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $returnRoute = (string) ($_POST['return_route'] ?? 'comments-today');
    $comment = find_comment_by_id($commentId);

    if (!$comment) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '注释不存在']);
            exit;
        }
        flash('error', '注释不存在');
        redirect('/?route=' . $returnRoute);
    }

    toggle_comment_done($commentId);
    dispatch_post_webhook('post.updated', (int) $comment['post_id']);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        $updatedComment = find_comment_by_id($commentId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_done' => (int) $updatedComment['is_done'],
            'comment_id' => $commentId
        ]);
        exit;
    }

    if ($returnRoute === 'post-view') {
        redirect('/?route=post-view&id=' . (int) $comment['post_id']);
    }

    redirect('/?route=' . $returnRoute);
}

if ($route === 'login') {
    render_header('登录');
    ?>
    <div style="max-width: 380px; margin: 80px auto;">
        <div class="panel-card" style="padding: 28px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div class="sidebar-logo" style="margin: 0 auto 10px; width: 42px; height: 42px; font-size: 20px;">M</div>
                <h2 style="font-size: 20px; font-weight: 600;">订货记录系统</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">内部协同 · 标签管理 · 代办跟踪</p>
            </div>
            <form method="post" action="/?route=login">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" placeholder="请输入用户名" required autofocus>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
                </div>
                <button class="btn-primary" type="submit" style="width: 100%; padding: 10px;">登 录</button>
            </form>
            <div style="margin-top: 16px; font-size: 12px; color: var(--text-subtle); text-align: center;">
                默认管理员：admin / admin123
            </div>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

$user = require_login();

// ==================== 主列表视图 (Memos 样式 Feed 流) ====================
if ($route === 'posts' || $route === 'my-posts' || $route === 'recycle-bin') {
    $filters = [
        'keyword' => trim((string) ($_GET['keyword'] ?? '')),
        'author_id' => (string) ($_GET['author_id'] ?? ''),
        'date_from' => (string) ($_GET['date_from'] ?? ''),
        'date_to' => (string) ($_GET['date_to'] ?? ''),
        'tag_id' => (string) ($_GET['tag_id'] ?? ''),
        'expected_ship_date' => (string) ($_GET['expected_ship_date'] ?? ''),
    ];

    if ($route === 'recycle-bin') {
        require_admin();
    }

    $posts = list_posts(
        $filters,
        $route === 'my-posts' ? 'mine' : 'all',
        (int) $user['id'],
        $route === 'recycle-bin'
    );
    $allTags = list_tags();

    $title = $route === 'my-posts' ? '我的记录' : ($route === 'recycle-bin' ? '回收站' : '订货时间流');
    render_header($title, $user);

    $hasActiveFilter = !empty($filters['keyword']) || !empty($filters['date_from']) || !empty($filters['tag_id']);
    ?>

    <?php if ($hasActiveFilter): ?>
        <div class="filter-active-bar">
            <div>
                🔍 当前筛选：
                <?php if (!empty($filters['keyword'])): ?>关键词 <strong>"<?= h($filters['keyword']) ?>"</strong>；<?php endif; ?>
                <?php if (!empty($filters['date_from'])): ?>日期 <strong><?= h($filters['date_from']) ?></strong>；<?php endif; ?>
                <?php if (!empty($filters['tag_id'])): ?>
                    <?php
                    $activeTagName = '';
                    foreach ($allTags as $t) { if ((int)$t['id'] === (int)$filters['tag_id']) { $activeTagName = $t['name']; break; } }
                    ?>
                    标签 <strong>#<?= h($activeTagName) ?></strong>；
                <?php endif; ?>
            </div>
            <a href="/?route=<?= h($route) ?>" style="font-weight: 600;">清除筛选 ✕</a>
        </div>
    <?php endif; ?>

    <!-- 顶部极速创建卡片 (此刻的想法... 仿 Memos 风格) -->
    <?php if ($route !== 'recycle-bin'): ?>
        <div class="memo-editor-card">
            <form method="post" action="/?route=post-store" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                <textarea id="quick-content-input" name="content" class="memo-textarea" placeholder="此刻的想法... 输入订货型号、数量、规格（支持 Ctrl+V 粘贴截图）" required></textarea>

                <!-- 待上传图片缩略图实时预览栏 -->
                <div class="memo-upload-previews" id="quick-image-preview-box" style="display: none;"></div>

                <div class="memo-editor-footer">
                    <div class="editor-tools">
                        <!-- 图片上传按钮 -->
                        <label class="tool-btn" title="上传或粘贴图片">
                            <span>📎 图片</span>
                            <input type="file" id="quick-image-input" name="images[]" multiple accept="image/*" style="display: none;">
                        </label>
                        <span id="quick-image-status" style="font-size: 12px; color: var(--primary);"></span>

                        <!-- 期望发货期 -->
                        <span style="font-size: 12px; color: var(--text-muted); margin-left: 4px;">发货期:</span>
                        <input type="date" name="expected_ship_date" class="tool-input-date" value="<?= h(today()) ?>" title="期望发货期" required>

                        <!-- 标签选择下拉 -->
                        <div class="tag-select-container">
                            <button type="button" class="tool-btn" onclick="toggleTagPopover(this)">
                                <span>🏷️ 标签 ▾</span>
                            </button>
                            <div class="tag-checkbox-popup">
                                <div style="font-size: 11px; font-weight: 600; color: var(--text-subtle); margin-bottom: 4px;">选择渠道标签</div>
                                <?php foreach ($allTags as $tag): ?>
                                    <label class="tag-check-label">
                                        <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>">
                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= h($tag['color']) ?>;"></span>
                                        <span><?= h($tag['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="editor-action">
                        <button class="btn-primary" type="submit">保存订货单</button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- 记录流 -->
    <div class="memos-stream">
        <?php if (!$posts): ?>
            <div class="panel-card empty-placeholder">
                <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                <div>暂无符合条件的订货记录</div>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php render_post_card($post, $user); ?>
                <?php if ($route === 'recycle-bin'): ?>
                    <div style="margin-top: -8px; margin-bottom: 16px; text-align: right;">
                        <form method="post" action="/?route=post-restore" style="display: inline;">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                            <button class="btn-primary" type="submit" style="background: var(--success); font-size: 12px; padding: 4px 10px;">♻️ 恢复此记录</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    render_footer();
    exit;
}

// ==================== 编辑订货单 ====================
if ($route === 'post-edit') {
    $postId = (int) ($_GET['id'] ?? 0);
    $post = find_post_by_id($postId);
    if (!$post || !can_edit_post($post, $user)) {
        flash('error', '无权编辑该订货记录');
        redirect('/?route=posts');
    }

    $images = get_post_images($postId);
    render_header('编辑订货记录', $user);
    ?>
    <div class="panel-card">
        <h2 class="panel-title">✏️ 编辑订货记录 #<?= (int) $post['id'] ?></h2>
        <form method="post" action="/?route=post-update" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">

            <div class="form-group">
                <label>订货内容</label>
                <textarea id="edit-content-input" name="content" class="form-control" rows="8" required><?= h($post['content']) ?></textarea>
            </div>

            <div class="form-group">
                <label>期望发货期</label>
                <input type="date" name="expected_ship_date" class="form-control" style="max-width: 200px;" value="<?= h($post['expected_ship_date']) ?>" required>
            </div>

            <?php if ($images): ?>
                <div class="form-group">
                    <label>现有图片（勾选将删除）</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px;">
                        <?php foreach ($images as $image): ?>
                            <div style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 6px; text-align: center;">
                                <img src="<?= h(image_url($image['file_path'])) ?>" alt="" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 4px;">
                                <label style="font-size: 11.5px; color: var(--danger); margin-top: 4px; display: flex; align-items: center; justify-content: center; gap: 4px; cursor: pointer;">
                                    <input type="checkbox" name="remove_image_ids[]" value="<?= (int) $image['id'] ?>"> 删除图片
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>追加图片附件 (支持选择文件或在上方内容中直接 Ctrl+V 粘贴截图)</label>
                <div class="memo-upload-previews" id="edit-image-preview-box" style="display: none; margin-bottom: 8px;"></div>
                <input type="file" id="edit-images-input" name="images[]" multiple accept="image/*" class="form-control">
                <div style="font-size: 12px; color: var(--text-subtle); margin-top: 4px;" id="edit-images-input-status"></div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button class="btn-primary" type="submit">保存修改</button>
                <a href="/?route=post-view&id=<?= (int) $post['id'] ?>" class="tool-btn" style="padding: 7px 16px;">返回详情</a>
            </div>
        </form>
    </div>
    <?php
    render_footer();
    exit;
}

// ==================== 订货单详情与注释协作 ====================
if ($route === 'post-view') {
    $postId = (int) ($_GET['id'] ?? 0);
    $post = find_post_by_id($postId);
    if (!$post) {
        flash('error', '订货记录不存在或已删除');
        redirect('/?route=posts');
    }

    $post['images'] = get_post_images($postId);
    $post['tags'] = get_post_tags($postId);
    $post['comment_count'] = count_post_comments($postId);
    $comments = get_post_comments($postId);
    $allTags = list_tags();
    $currentTagIds = array_map(static function ($tag) { return (int) $tag['id']; }, $post['tags']);

    render_header('订货记录详情', $user);
    render_post_card($post, $user);
    ?>

    <!-- 标签管理面板 -->
    <div class="panel-card">
        <h3 class="panel-title" style="font-size: 14px;">🏷️ 渠道协作标签</h3>
        <form method="post" action="/?route=post-tags">
            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                <?php foreach ($allTags as $tag): ?>
                    <label class="tool-btn" style="cursor: pointer;">
                        <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array((int) $tag['id'], $currentTagIds, true) ? 'checked' : '' ?>>
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= h($tag['color']) ?>;"></span>
                        <span><?= h($tag['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="btn-primary" type="submit" style="font-size: 12px; padding: 5px 12px;">保存标签变更</button>
        </form>
    </div>

    <!-- 注释与待办事项 -->
    <div class="panel-card">
        <h3 class="panel-title" style="font-size: 14px;">💬 跟进注释与待办 (<?= count($comments) ?>)</h3>

        <form method="post" action="/?route=comment-add" style="margin-bottom: 18px;">
            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
            <div class="form-group">
                <textarea name="content" class="form-control" rows="3" required placeholder="添加跟进说明、渠道反馈或代办事项..."></textarea>
            </div>
            <button class="btn-primary" type="submit" style="font-size: 12.5px; padding: 6px 14px;">发表注释</button>
        </form>

        <div class="comment-section-box">
            <?php if (!$comments): ?>
                <div style="text-align: center; color: var(--text-subtle); padding: 12px; font-size: 13px;">暂无注释记录</div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-row <?= (int) $comment['is_done'] === 1 ? 'done' : '' ?>">
                        <form method="post" action="/?route=comment-toggle">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                            <input type="hidden" name="return_route" value="post-view">
                            <input type="checkbox" onchange="this.form.submit()" <?= (int) $comment['is_done'] === 1 ? 'checked' : '' ?> title="标记完成状态">
                        </form>
                        <div class="comment-body">
                            <div><?= nl2br(h($comment['content'])) ?></div>
                            <div class="comment-author-time">
                                <?= h($comment['user_name']) ?> · <?= h(date('m-d H:i', strtotime($comment['created_at']))) ?>
                                <?php if ((int) $comment['is_done'] === 1 && !empty($comment['done_at'])): ?>
                                    · 已完成于 <?= h(date('m-d H:i', strtotime($comment['done_at']))) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

// ==================== 今日待办注释汇总 ====================
if ($route === 'comments-today') {
    $status = (string) ($_GET['status'] ?? 'all');
    $comments = list_today_comments($status);

    render_header('今日待办注释', $user);
    ?>
    <div class="panel-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 class="panel-title" style="margin: 0;">✅ 今日注释与待办清单</h2>
            <div>
                <a href="/?route=comments-today&status=all" class="tool-btn <?= $status === 'all' ? 'active' : '' ?>">全部</a>
                <a href="/?route=comments-today&status=pending" class="tool-btn <?= $status === 'pending' ? 'active' : '' ?>">未完成</a>
                <a href="/?route=comments-today&status=done" class="tool-btn <?= $status === 'done' ? 'active' : '' ?>">已完成</a>
            </div>
        </div>

        <div class="comment-section-box" style="border-top: none; padding-top: 0;">
            <?php if (!$comments): ?>
                <div class="empty-placeholder">今天还没有产生注释记录</div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-row <?= (int) $comment['is_done'] === 1 ? 'done' : '' ?>" style="background: #ffffff; border: 1px solid var(--border-color); padding: 12px;">
                        <form method="post" action="/?route=comment-toggle">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                            <input type="hidden" name="return_route" value="comments-today">
                            <input type="checkbox" onchange="this.form.submit()" <?= (int) $comment['is_done'] === 1 ? 'checked' : '' ?> style="margin-top: 4px;">
                        </form>
                        <div class="comment-body">
                            <div style="font-size: 14px; font-weight: 500;"><?= nl2br(h($comment['content'])) ?></div>
                            <div class="comment-author-time" style="margin-top: 6px;">
                                注释人：<strong><?= h($comment['user_name']) ?></strong> · <?= h($comment['created_at']) ?>
                            </div>
                            <div style="margin-top: 6px; font-size: 12px; color: var(--text-muted); background: #f8fafc; padding: 6px 10px; border-radius: 4px;">
                                关联订货单：<a href="/?route=post-view&id=<?= (int) $comment['post_id'] ?>" style="color: var(--primary); font-weight: 500;">
                                    #<?= (int) $comment['post_id'] ?> (<?= h($comment['post_author_name']) ?>): <?= h(mb_strimwidth($comment['post_content'], 0, 50, '...')) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

// ==================== 旧路由兼容重定向 ====================
if ($route === 'tags') {
    redirect('/?route=settings&tab=tags');
}
if ($route === 'users') {
    redirect('/?route=settings&tab=users');
}
if ($route === 'webhooks') {
    redirect('/?route=settings&tab=webhooks');
}

// ==================== 系统与个人设置中心 (Memos Settings) ====================
if ($route === 'settings') {
    $currentTab = (string) ($_GET['tab'] ?? (is_admin() ? 'tags' : 'profile'));
    if (!is_admin() && in_array($currentTab, ['tags', 'users', 'webhooks'], true)) {
        $currentTab = 'profile';
    }

    render_header('设置中心', $user);
    ?>
    <div class="panel-card" style="padding-bottom: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 style="font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <span>⚙️ 系统设置</span>
            </h2>
        </div>

        <nav class="settings-tab-nav">
            <?php if (is_admin()): ?>
                <a href="/?route=settings&tab=tags" class="settings-tab-link <?= $currentTab === 'tags' ? 'active' : '' ?>">
                    🏷️ 标签管理
                </a>
                <a href="/?route=settings&tab=users" class="settings-tab-link <?= $currentTab === 'users' ? 'active' : '' ?>">
                    👥 用户管理
                </a>
                <a href="/?route=settings&tab=webhooks" class="settings-tab-link <?= $currentTab === 'webhooks' ? 'active' : '' ?>">
                    ⚡ Webhook
                </a>
            <?php endif; ?>
            <a href="/?route=settings&tab=profile" class="settings-tab-link <?= $currentTab === 'profile' ? 'active' : '' ?>">
                👤 账户与个人设置
            </a>
        </nav>
    </div>

    <?php if ($currentTab === 'tags' && is_admin()): ?>
        <?php $tags = list_tags(false); ?>
        <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 20px;">
            <div class="panel-card">
                <h3 class="panel-title">➕ 新增标签</h3>
                <form method="post" action="/?route=tag-save">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div class="form-group">
                        <label>标签名称</label>
                        <input type="text" name="name" class="form-control" placeholder="如：仓库、工厂、加急" required>
                    </div>
                    <div class="form-group">
                        <label>标签颜色</label>
                        <input type="color" name="color" value="#2563eb" style="height: 38px; width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-sm); cursor: pointer;">
                    </div>
                    <div class="form-group">
                        <label>显示排序 (越小越靠前)</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <button class="btn-primary" type="submit" style="margin-top: 10px; width: 100%;">创建标签</button>
                </form>
            </div>

            <div class="panel-card">
                <h3 class="panel-title">🏷️ 现有标签</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($tags as $tag): ?>
                        <form method="post" action="/?route=tag-save" style="display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                            <input type="text" name="name" class="form-control" value="<?= h($tag['name']) ?>" required style="flex: 1;">
                            <input type="color" name="color" value="<?= h($tag['color']) ?>" style="width: 42px; height: 34px; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;">
                            <input type="number" name="sort_order" class="form-control" value="<?= (int) $tag['sort_order'] ?>" style="width: 70px;" title="排序">
                            <label style="font-size: 12px; display: flex; align-items: center; gap: 4px; white-space: nowrap; cursor: pointer;">
                                <input type="checkbox" name="is_active" value="1" <?= (int) $tag['is_active'] === 1 ? 'checked' : '' ?>> 启用
                            </label>
                            <button class="btn-primary" type="submit" style="font-size: 12px; padding: 6px 12px;">保存</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php elseif ($currentTab === 'users' && is_admin()): ?>
        <?php $users = list_users(); ?>
        <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 20px;">
            <div class="panel-card">
                <h3 class="panel-title">➕ 新增用户</h3>
                <form method="post" action="/?route=user-save">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div class="form-group">
                        <label>用户名 (登录账号)</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>显示姓名</label>
                        <input type="text" name="display_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>初始密码</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>角色权限</label>
                        <select name="role" class="form-control">
                            <option value="user">普通用户 (订货员)</option>
                            <option value="admin">系统管理员</option>
                        </select>
                    </div>
                    <button class="btn-primary" type="submit" style="margin-top: 10px; width: 100%;">创建用户</button>
                </form>
            </div>

            <div class="panel-card">
                <h3 class="panel-title">👥 用户列表</h3>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>显示名</th>
                        <th>角色</th>
                        <th>注册时间</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $item): ?>
                        <tr>
                            <td><?= (int) $item['id'] ?></td>
                            <td><strong><?= h($item['username']) ?></strong></td>
                            <td><?= h($item['display_name']) ?></td>
                            <td>
                                <span class="tag-pill" style="background: <?= $item['role'] === 'admin' ? '#fef3c7; color: #92400e;' : '#e0e7ff; color: #3730a3;' ?>">
                                    <?= $item['role'] === 'admin' ? '管理员' : '普通用户' ?>
                                </span>
                            </td>
                            <td><?= h(date('Y-m-d', strtotime($item['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($currentTab === 'webhooks' && is_admin()): ?>
        <?php
        $webhooks = list_webhooks();
        $logs = list_recent_webhook_logs();
        ?>
        <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 20px;">
            <div class="panel-card">
                <h3 class="panel-title">➕ 添加 Webhook 订阅</h3>
                <form method="post" action="/?route=webhook-save">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div class="form-group">
                        <label>订阅名称</label>
                        <input type="text" name="name" class="form-control" placeholder="如：钉钉机器人 / ERP同步" required>
                    </div>
                    <div class="form-group">
                        <label>推送目标 URL</label>
                        <input type="url" name="target_url" class="form-control" placeholder="https://..." required>
                    </div>
                    <div class="form-group">
                        <label>验签 Secret Key</label>
                        <input type="text" name="secret" class="form-control" placeholder="自定义密钥字符串" required>
                    </div>
                    <button class="btn-primary" type="submit" style="margin-top: 10px; width: 100%;">添加订阅</button>
                </form>
            </div>

            <div class="panel-card">
                <h3 class="panel-title">⚡ 订阅列表</h3>
                <?php if (!$webhooks): ?>
                    <div class="empty-placeholder">暂未配置 Webhook</div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($webhooks as $hook): ?>
                            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600;"><?= h($hook['name']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-subtle); margin-top: 2px;"><?= h($hook['target_url']) ?></div>
                                </div>
                                <form method="post" action="/?route=webhook-toggle">
                                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $hook['id'] ?>">
                                    <button class="tool-btn" type="submit" style="color: <?= (int) $hook['is_active'] === 1 ? 'var(--success)' : 'var(--text-subtle)' ?>;">
                                        <?= (int) $hook['is_active'] === 1 ? '● 运行中' : '○ 已停用' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel-card" style="margin-top: 20px;">
            <h3 class="panel-title">📜 最近推送日志</h3>
            <table class="data-table">
                <thead>
                <tr>
                    <th>时间</th>
                    <th>订阅名称</th>
                    <th>事件</th>
                    <th>结果</th>
                    <th>HTTP状态</th>
                    <th>错误详情</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-subtle);">暂无日志</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= h(date('m-d H:i:s', strtotime($log['created_at']))) ?></td>
                            <td><?= h($log['webhook_name'] ?? '未知') ?></td>
                            <td><code><?= h($log['event_type']) ?></code></td>
                            <td>
                                <span class="tag-pill" style="background: <?= (int) $log['is_success'] === 1 ? '#dcfce7; color: #15803d;' : '#fee2e2; color: #b91c1c;' ?>">
                                    <?= (int) $log['is_success'] === 1 ? '成功' : '失败' ?>
                                </span>
                            </td>
                            <td><?= h((string) $log['response_status']) ?></td>
                            <td><small><?= h((string) $log['error_message']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($currentTab === 'profile'): ?>
        <div class="panel-card">
            <h3 class="panel-title">👤 个人资料与安全</h3>
            <div style="display: grid; grid-template-columns: 220px 1fr; gap: 32px; align-items: start; margin-top: 16px;">
                <!-- 左侧账号概览卡片 -->
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 24px 16px; text-align: center;">
                    <div class="user-avatar" style="width: 64px; height: 64px; font-size: 26px; margin: 0 auto 12px;">
                        <?= mb_substr($user['display_name'], 0, 1, 'UTF-8') ?>
                    </div>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text-main);"><?= h($user['display_name']) ?></div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">@<?= h($user['username']) ?></div>
                    <div style="margin-top: 12px;">
                        <span class="tag-pill" style="background: <?= $user['role'] === 'admin' ? '#fef3c7; color: #92400e;' : '#e0e7ff; color: #3730a3;' ?>">
                            <?= $user['role'] === 'admin' ? '系统管理员' : '订货员' ?>
                        </span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-subtle); margin-top: 16px; border-top: 1px solid var(--border-subtle); padding-top: 12px;">
                        创建时间：<?= h(date('Y-m-d', strtotime($user['created_at']))) ?>
                    </div>
                </div>

                <!-- 右侧表单 -->
                <form method="post" action="/?route=profile-save">
                    <input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
                    <div class="form-group">
                        <label>登录账号 (不可变更)</label>
                        <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled style="background: #f1f5f9; color: var(--text-muted);">
                    </div>
                    <div class="form-group">
                        <label>显示姓名</label>
                        <input type="text" name="display_name" class="form-control" value="<?= h($user['display_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>修改密码 (留空则保持原密码不变)</label>
                        <input type="password" name="password" class="form-control" placeholder="输入新的登录密码">
                    </div>
                    <div style="margin-top: 16px;">
                        <button class="btn-primary" type="submit" style="padding: 8px 24px;">保存个人信息</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php
    render_footer();
    exit;
}

flash('error', '页面不存在');
redirect('/?route=posts');

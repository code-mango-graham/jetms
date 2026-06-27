(function () {
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getCurriculumId() {
        const params = new URLSearchParams(window.location.search);
        return params.get('curriculum_id') || '';
    }

    function renderTree(levels) {
        const container = $('#levelsContainer');
        container.empty();

        if (!levels || levels.length === 0) {
            $('#emptyState').removeClass('d-none');
            return;
        }

        $('#emptyState').addClass('d-none');

        levels.forEach(function (level) {
            const levelGroup = $(
                `<div class="level-group">
                    <div class="level-title">${escapeHtml(level.level_name)}</div>
                    <div class="subjects-wrap"></div>
                </div>`
            );

            const subjectsWrap = levelGroup.find('.subjects-wrap');

            if (!level.subjects || level.subjects.length === 0) {
                subjectsWrap.append('<p class="subject-line text-muted">No subjects</p>');
            } else {
                level.subjects.forEach(function (subject) {
                    const code = subject.subject_code ? escapeHtml(subject.subject_code) : '-';
                    const name = escapeHtml(subject.subject_name);
                    subjectsWrap.append(
                        `<p class="subject-line">${code} - ${name}</p>`
                    );
                });
            }

            container.append(levelGroup);
        });
    }

    function loadTree() {
        const curriculum_id = getCurriculumId();

        if (!curriculum_id) {
            $('#errorState').removeClass('d-none').text('Missing curriculum id.');
            return;
        }

        $.ajax({
            url: `../config/curriculum_tree_view.php?curriculum_id=${encodeURIComponent(curriculum_id)}`,
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (!res || res.status !== 'success') {
                    $('#errorState').removeClass('d-none').text(res && res.message ? res.message : 'Failed to load curriculum.');
                    return;
                }

                $('#title').text(res.curriculum.curriculum_name || 'Curriculum');
                $('#description').text(res.curriculum.description || '');
                renderTree(res.levels || []);
            },
            error: function () {
                $('#errorState').removeClass('d-none').text('Unable to load curriculum tree.');
            }
        });
    }

    loadTree();
})();

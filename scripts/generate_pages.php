<?php

declare(strict_types=1);

/**
 * One-off generator for the Iterp master/annual settings + SIS resource pages.
 *
 * Each generated page is a clone of `general.html` (the full Edudash layout with
 * sidebar + navbar) whose <main> content is replaced by a shared CRUD card that
 * lists/adds/deletes records through `window.iterp.<resource>`. The inline
 * <script> is executed by the SPA's PageRenderer; `window.iterp` is exposed from
 * `src/api/client.ts`.
 *
 * Usage: php scripts/generate_pages.php
 */

$base = __DIR__ . '/../../client/src/pages/raw/general.html';
$outDir = __DIR__ . '/../../client/src/pages/raw/';

$html = file_get_contents($base);
if ($html === false) {
    fwrite(STDERR, "Base template not found: {$base}\n");
    exit(1);
}

/**
 * [filename, window.iterp key, page title].
 * Keys must match the `api` exports in src/api/client.ts.
 */
$pages = [
    ['organizations', 'firms', 'Organizations (Firm)'],
    ['countries', 'countries', 'Country'],
    ['states', 'states', 'State'],
    ['cities', 'cities', 'City'],
    ['titles', 'titles', 'Title'],
    ['academic-years', 'academicYears', 'Academic Year'],
    ['school-calendar', 'schoolCalendar', 'School Calendar'],
    ['custom-field-categories', 'customFieldCategories', 'Custom Field Categories'],
    ['custom-fields', 'customFields', 'Custom Fields'],
    ['attendance-legends', 'attendanceLegends', 'Attendance Legends'],
    ['documents', 'documents', 'Documents'],
    ['classes', 'classes', 'Class'],
    ['sections', 'sections', 'Sections'],
    ['groups', 'groups', 'Group'],
];
/**
 * Build the page body (list + add form) with the resource name substituted.
 */
function buildPage(string $title, string $key): string
{
    $submenu = '';
    $script  = '<script>(function () {'
        . 'var key = \'' . $key . '\';'
        . 'var api = (window.iterp && window.iterp[key]) || null;'
        . ' var tbody = document.getElementById("iterp-list");'
        . ' var form = document.getElementById("iterp-add-form");'
        . ' var msg = document.getElementById("iterp-form-msg");'
        . ' if (!api) { if (tbody) tbody.innerHTML = \'<tr><td colspan="4">API client unavailable.</td></tr>\'; return; }'
        . ' function render(items){ if(!items||!items.length){ tbody.innerHTML=\'<tr><td colspan="4">No records.</td></tr>\'; return; }'
        . '  tbody.innerHTML = items.map(function(it){ return \'<tr><td>\'+it.id+\'</td><td>\'+(it.name||it.first_name||"")+\'</td><td>\'+(it.short_name||it.username||"-")+\'</td>'
        . ' <td class="text-end"><button class="btn btn-danger btn-sm iterp-delete" data-id="\'+it.id+\'">Delete</button></td></tr>\'; }).join(""); }'
        . ' function refresh(){ tbody.innerHTML=\'<tr><td colspan="4">Loading…</td></tr>\';'
        . ' api.list().then(render).catch(function(e){ tbody.innerHTML=\'<tr><td colspan="4" class="text-danger">\'+((e&&e.message)||"Error")+\'</td></tr>\'; }); }'
        . ' if(tbody) tbody.addEventListener("click", function(e){ var b=e.target.closest(".iterp-delete"); if(!b) return;'
        . ' api.remove(b.getAttribute("data-id")).then(refresh).catch(function(e){ alert((e&&e.message)||"Delete failed"); }); });'
        . ' if(form) form.addEventListener("submit", function(e){ e.preventDefault(); var d={ name:form.name.value, short_name:form.short_name.value };'
        . ' api.create(d).then(function(){ if(msg) msg.textContent="Added."; form.reset(); refresh(); })'
        . ' .catch(function(e){ if(msg) msg.textContent=(e&&e.message)||"Add failed"; }); });'
        . ' refresh();'
        . '})();</script>';

    return '<div class="dashboard-main-body">'
        . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">'
        . '<h6 class="fw-semibold mb-0">' . $title . '</h6></div>'
        . '<div class="card"><div class="card-header"><h6 class="fw-semibold mb-0">Add ' . $title . '</h6></div>'
        . '<div class="card-body p-24"><form id="iterp-add-form" class="row gy-3 align-items-end">'
        . '<div class="col-md-4"><label class="form-label">Short Name</label>'
        . '<input type="text" name="short_name" class="form-control" placeholder="e.g. IN"></div>'
        . '<div class="col-md-4"><label class="form-label">Name</label>'
        . '<input type="text" name="name" class="form-control" placeholder="Name"></div>'
        . '<div class="col-md-4"><button type="submit" class="btn btn-primary-600">Add Record</button>'
        . '<span id="iterp-form-msg" class="ms-2"></span></div>'
        . '</form></div></div>'
        . '<div class="card mt-24"><div class="card-header"><h6 class="fw-semibold mb-0">All ' . $title . '</h6></div>'
        . '<div class="card-body p-24"><div class="table-responsive">'
        . '<table class="table text-nowrap align-middle mb-0"><thead><tr><th>#</th><th>Name</th><th>Short Name</th><th>Action</th></tr></thead>'
        . '<tbody id="iterp-list"><tr><td colspan="4" class="text-center">Loading…</td></tr></tbody></table>'
        . '</div></div></div>'
        . $script
        . '</div>';
}

foreach ($pages as $page) {
    [$name, $key, $title] = $page;

    $out = preg_replace_callback(
        '~(<div class="dashboard-main-body">)[\s\S]*?(</main>)~',
        static fn(array $m): string => buildPage($title, $key) . $m[2],
        $html,
        1,
    );
    $out = preg_replace('/<title>[\s\S]*?<\/title>/', '<title>' . $title . ' - Iterp</title>', $out, 1);

    $file = $outDir . '/' . $name . '.html';
    file_put_contents($file, $out);
    echo 'Generated ' . $file . PHP_EOL;
}
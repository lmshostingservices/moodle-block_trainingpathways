<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Training Pathways student dashboard block.
 *
 * Shows the current user their assigned training pathway(s) — colour-coded
 * progress bar, stage breakdown, target-date countdown, and a link to the
 * full view page. Gracefully hidden when local_trainingpathways is not installed
 * or the user has no assigned pathway.
 *
 * @package    block_trainingpathways
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_trainingpathways extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_trainingpathways');
    }

    public function applicable_formats() {
        return [
            'my'             => true,
            'site'           => true,
            'course'         => true,
            'course-view'    => true,
            'mod'            => false,
        ];
    }

    public function has_config() {
        return false;
    }

    public function get_content() {
        global $DB, $USER, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // ── Guard: local_trainingpathways must be installed ──────────────────
        if (!$DB->get_manager()->table_exists('local_tp_assignments')) {
            $this->content->text = $this->render_empty(
                get_string('not_installed', 'block_trainingpathways'), '&#9888;'
            );
            return $this->content;
        }

        // ── Guard: user must have the view capability ────────────────────────
        $context = context_system::instance();
        if (!isloggedin() || isguestuser() ||
            !has_capability('local/trainingpathways:view', $context)) {
            $this->content->text = '';
            return $this->content;
        }

        // ── Load user's assignments (active + completed) ─────────────────────
        $assignments = $DB->get_records_sql(
            "SELECT a.*, p.name AS pathwayname, p.colour AS pathwaycolour, p.description AS pathwaydesc
               FROM {local_tp_assignments} a
               JOIN {local_tp_pathways} p ON p.id = a.pathwayid
              WHERE a.userid = :uid AND a.status IN ('active','completed')
              ORDER BY
                CASE WHEN a.status = 'active' THEN 0 ELSE 1 END,
                a.timeassigned DESC",
            ['uid' => (int)$USER->id]
        );

        if (empty($assignments)) {
            $this->content->text = $this->render_empty(
                get_string('no_pathway', 'block_trainingpathways'), '&#127775;'
            );
            return $this->content;
        }

        // ── Render one card per assignment ───────────────────────────────────
        $this->page->requires->css('/blocks/trainingpathways/styles.css');

        $html = '';
        foreach ($assignments as $assignment) {
            $html .= $this->render_pathway_card($assignment);
        }

        $this->content->text = $html;
        return $this->content;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Render a compact card for one pathway assignment.
     */
    private function render_pathway_card(stdClass $a): string {
        global $DB;

        $colour    = $this->colour_hex($a->pathwaycolour);
        $viewUrl   = (new moodle_url('/local/trainingpathways/view.php',
            ['assignmentid' => $a->id]))->out();

        // Progress: count required tasks done vs. total required.
        $totalRequired = (int)$DB->count_records_sql(
            "SELECT COUNT(t.id)
               FROM {local_tp_tasks} t
               JOIN {local_tp_stages} s ON s.id = t.stageid
              WHERE s.pathwayid = :pid AND t.required = 1",
            ['pid' => $a->pathwayid]
        );
        $doneRequired = 0;
        if ($totalRequired > 0) {
            $doneRequired = (int)$DB->count_records_sql(
                "SELECT COUNT(p.id)
                   FROM {local_tp_progress} p
                   JOIN {local_tp_tasks}    t ON t.id = p.taskid
                   JOIN {local_tp_stages}   s ON s.id = t.stageid
                  WHERE p.assignmentid = :aid AND p.status = 'complete' AND t.required = 1",
                ['aid' => $a->id]
            );
        }
        $percent = ($totalRequired > 0) ? (int)round($doneRequired / $totalRequired * 100) : 0;

        // Stages with per-stage done/total counts.
        $stages = $DB->get_records('local_tp_stages',
            ['pathwayid' => $a->pathwayid], 'sortorder ASC');

        // Build progress map for this assignment.
        $progressRows = $DB->get_records('local_tp_progress', ['assignmentid' => $a->id]);
        $progressMap  = [];
        foreach ($progressRows as $pr) {
            $progressMap[$pr->taskid] = $pr->status;
        }

        // Badge.
        $isComplete  = ($a->status === 'completed');
        $badgeClass  = $isComplete ? 'complete' : 'active';
        $badgeLabel  = $isComplete
            ? get_string('completed_badge', 'block_trainingpathways')
            : get_string('active_badge',    'block_trainingpathways');

        // Target date.
        $targetHtml = '';
        if ($a->targetdate && !$isComplete) {
            $diff = (int)(($a->targetdate - time()) / 86400);
            if ($diff < 0) {
                $targetHtml = '<div class="tp-block-target overdue">&#9888; '
                    . get_string('days_overdue', 'block_trainingpathways', abs($diff))
                    . '</div>';
            } else {
                $targetHtml = '<div class="tp-block-target ok">&#128197; '
                    . get_string('days_remaining', 'block_trainingpathways', $diff)
                    . '</div>';
            }
        }

        // Stage mini-list (max 5 shown to keep block compact).
        $stageListHtml = '';
        if (!empty($stages)) {
            $stageListHtml .= '<ul class="tp-block-stages">';
            $shown = 0;
            foreach ($stages as $stage) {
                if ($shown >= 5) {
                    $remaining = count($stages) - $shown;
                    $stageListHtml .= '<li class="tp-block-stage-row" style="color:#868e96;font-style:italic">'
                        . '+ ' . $remaining . ' more stage' . ($remaining > 1 ? 's' : '') . '</li>';
                    break;
                }
                $tasks = $DB->get_records('local_tp_tasks', ['stageid' => $stage->id]);
                $stageDone  = 0;
                $stageTotal = count($tasks);
                foreach ($tasks as $task) {
                    if (isset($progressMap[$task->id]) && $progressMap[$task->id] === 'complete') {
                        $stageDone++;
                    }
                }
                $stageStatus = 'notstarted';
                if ($stageDone > 0 && $stageDone >= $stageTotal) {
                    $stageStatus = 'complete';
                } elseif ($stageDone > 0) {
                    $stageStatus = 'inprogress';
                }
                $stageListHtml .= '<li class="tp-block-stage-row">'
                    . '<span class="tp-block-stage-dot ' . $stageStatus . '"></span>'
                    . '<span class="tp-block-stage-name">' . s($stage->name) . '</span>'
                    . '<span class="tp-block-stage-count">' . $stageDone . '/' . $stageTotal . '</span>'
                    . '</li>';
                $shown++;
            }
            $stageListHtml .= '</ul>';
        }

        // Completed banner.
        $completeBanner = '';
        if ($isComplete) {
            $completeBanner = '<div class="tp-block-complete-banner">&#10003; Pathway Complete</div>';
        }

        return '
<div class="tp-block-card">
  <div class="tp-block-stripe" style="background:' . s($colour) . '"></div>
  <div class="tp-block-body">
    <div class="tp-block-title-row">
      <div class="tp-block-name">' . s($a->pathwayname) . '</div>
      <span class="tp-block-badge ' . $badgeClass . '">' . $badgeLabel . '</span>
    </div>
    ' . $targetHtml . '
    <div class="tp-block-progress-label">
      <strong>' . get_string('overall_progress', 'block_trainingpathways') . '</strong>
      <span>' . get_string('tasks_complete', 'block_trainingpathways',
            (object)['done' => $doneRequired, 'total' => $totalRequired]) . '</span>
    </div>
    <div class="tp-block-progress-wrap">
      <div class="tp-block-progress-bar"
           style="width:' . $percent . '%;background:' . s($colour) . '"></div>
    </div>
    ' . $stageListHtml . '
    ' . $completeBanner . '
    <a href="' . $viewUrl . '" class="tp-block-btn">'
        . get_string('view_full', 'block_trainingpathways') . '</a>
  </div>
</div>';
    }

    /**
     * Render a centred empty / info state.
     */
    private function render_empty(string $message, string $icon = '&#127775;'): string {
        return '<div class="tp-block-empty">'
            . '<span class="tp-block-empty-icon">' . $icon . '</span>'
            . s($message)
            . '</div>';
    }

    /**
     * Map colour name to hex value (mirrors local_trainingpathways_colour_class).
     */
    private function colour_hex(string $colour): string {
        $map = [
            'blue'   => '#1565c0',
            'green'  => '#2e7d32',
            'red'    => '#c62828',
            'orange' => '#e65100',
            'purple' => '#6a1b9a',
            'teal'   => '#00695c',
            'indigo' => '#283593',
            'pink'   => '#880e4f',
        ];
        return $map[$colour] ?? '#1565c0';
    }
}

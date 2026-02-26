<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_role(['admin', 'manager']);
$tenantId = (int)$user['tenant_id'];
$resultText = '';
$error = '';
$mode = $_POST['mode'] ?? 'task_plan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    if (!ai_is_configured()) {
        $error = 'AI is not configured. Set OPENAI_API_KEY in your environment and restart the server.';
    } else {
        if ($mode === 'task_plan') {
            $goal = trim((string)($_POST['goal'] ?? ''));
            $constraints = trim((string)($_POST['constraints'] ?? ''));
            if ($goal === '') {
                $error = 'Goal is required for task planning.';
            } else {
                $system = 'You are an operations assistant for a company task management platform. Create practical implementation plans.';
                $prompt = "Create a detailed execution plan for this goal. Include:\n"
                    . "1) Task breakdown\n2) Suggested owners\n3) Estimated effort (hours)\n4) Priority\n5) Risks\n"
                    . "Goal: {$goal}\nConstraints: {$constraints}";
                $res = ai_generate_text($system, $prompt);
                if ($res['ok']) {
                    $resultText = $res['text'];
                } else {
                    $error = $res['error'];
                }
            }
        } elseif ($mode === 'daily_draft') {
            $notes = trim((string)($_POST['notes'] ?? ''));
            if ($notes === '') {
                $error = 'Raw notes are required to draft daily report.';
            } else {
                $system = 'You draft concise professional employee daily reports.';
                $prompt = "Convert the raw notes into a structured daily report with sections: Work Summary, Blockers, Next Plan.\nNotes:\n{$notes}";
                $res = ai_generate_text($system, $prompt);
                if ($res['ok']) {
                    $resultText = $res['text'];
                } else {
                    $error = $res['error'];
                }
            }
        } elseif ($mode === 'hr_policy') {
            $question = trim((string)($_POST['question'] ?? ''));
            if ($question === '') {
                $error = 'HR question is required.';
            } else {
                $system = 'You are an HR operations advisor. Provide practical, neutral policy draft guidance.';
                $prompt = "Provide a practical policy draft answer for this HR operations question:\n{$question}";
                $res = ai_generate_text($system, $prompt);
                if ($res['ok']) {
                    $resultText = $res['text'];
                } else {
                    $error = $res['error'];
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AI Assistant - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>AI Assistant</h1>
            <p class="subtitle">Task planning, daily report drafting, and HR support.</p>
        </div>
        <a class="button secondary" href="<?= (($user['role'] ?? '') === 'admin') ? '/admin.php' : '/manager.php' ?>">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="grid">
        <div class="card">
            <h2>Task Plan Generator</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="mode" value="task_plan">
                <label>
                    Goal
                    <textarea name="goal" rows="3" required><?= htmlspecialchars((string)($_POST['goal'] ?? '')) ?></textarea>
                </label>
                <label>
                    Constraints
                    <textarea name="constraints" rows="2"><?= htmlspecialchars((string)($_POST['constraints'] ?? '')) ?></textarea>
                </label>
                <button type="submit">Generate plan</button>
            </form>
        </div>

        <div class="card">
            <h2>Daily Report Draft</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="mode" value="daily_draft">
                <label>
                    Raw notes
                    <textarea name="notes" rows="5" required><?= htmlspecialchars((string)($_POST['notes'] ?? '')) ?></textarea>
                </label>
                <button type="submit">Draft report</button>
            </form>
        </div>

        <div class="card">
            <h2>HR Assistant</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="mode" value="hr_policy">
                <label>
                    HR question
                    <textarea name="question" rows="4" required><?= htmlspecialchars((string)($_POST['question'] ?? '')) ?></textarea>
                </label>
                <button type="submit">Get guidance</button>
            </form>
        </div>
    </section>

    <?php if ($resultText !== ''): ?>
        <section class="card ai-result">
            <h2>AI Output</h2>
            <pre><?= htmlspecialchars($resultText) ?></pre>
        </section>
    <?php endif; ?>
</div>
</body>
</html>

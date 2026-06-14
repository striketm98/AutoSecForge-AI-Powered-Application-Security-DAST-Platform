<?php
// Ideas & Feedback board API.
//   GET                      → list ideas (with vote/comment counts, my-vote)
//   GET ?id=N                → single idea + its comments
//   POST {action:create}     → new idea          (any authenticated user)
//   POST {action:comment}    → comment on idea    (any authenticated user)
//   POST {action:vote}       → toggle upvote      (any authenticated user)
//   POST {action:status}     → set status         (admin/manager only)
//   POST {action:delete}     → delete idea        (admin, or the author)
require_once '../../src/auth.php';
require_once '../../src/helpers.php';
require_auth();
header('Content-Type: application/json');

$uid  = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['user_role'] ?? '';
$isManager = in_array($role, ['admin', 'manager'], true);

function out($d, int $code = 200){ http_response_code($code); echo json_encode($d); exit; }

try {
    $pdo = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in     = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $in['action'] ?? '';

        if ($action === 'create') {
            $title = trim($in['title'] ?? '');
            $body  = trim($in['body'] ?? '');
            $cat   = in_array($in['category'] ?? '', ['idea','feedback','bug','question'], true)
                       ? $in['category'] : 'idea';
            if ($title === '') out(['error' => 'Title is required'], 422);
            $pdo->prepare('INSERT INTO ideas (user_id, title, body, category) VALUES (?, ?, ?, ?)')
                ->execute([$uid, mb_substr($title, 0, 200), $body, $cat]);
            $id = (int)$pdo->lastInsertId();
            asf_audit('idea.create', "id=$id cat=$cat");
            // Notify admins/managers that there's new feedback to review.
            asf_notify(
                $pdo->query("SELECT id FROM users WHERE role IN ('admin','manager')")->fetchAll(PDO::FETCH_COLUMN),
                'New ' . $cat . ' submitted', mb_substr($title, 0, 120),
                'ideas.php?id=' . $id, 'info'
            );
            out(['ok' => true, 'id' => $id]);
        }

        if ($action === 'comment') {
            $iid  = (int)($in['idea_id'] ?? 0);
            $body = trim($in['body'] ?? '');
            if (!$iid || $body === '') out(['error' => 'idea_id and body required'], 422);
            $pdo->prepare('INSERT INTO idea_comments (idea_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$iid, $uid, $body]);
            asf_audit('idea.comment', "idea=$iid");
            // Notify the idea's author (if it wasn't them commenting).
            $author = (int)$pdo->query('SELECT user_id FROM ideas WHERE id=' . $iid)->fetchColumn();
            if ($author && $author !== $uid) {
                asf_notify([$author], 'New comment on your post', mb_substr($body, 0, 120),
                           'ideas.php?id=' . $iid, 'info');
            }
            out(['ok' => true]);
        }

        if ($action === 'vote') {
            $iid = (int)($in['idea_id'] ?? 0);
            if (!$iid) out(['error' => 'idea_id required'], 422);
            // Toggle: delete if present, else insert.
            $del = $pdo->prepare('DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?');
            $del->execute([$iid, $uid]);
            if ($del->rowCount() === 0) {
                $pdo->prepare('INSERT INTO idea_votes (idea_id, user_id) VALUES (?, ?)')
                    ->execute([$iid, $uid]);
                $voted = true;
            } else {
                $voted = false;
            }
            $n = (int)$pdo->query('SELECT COUNT(*) FROM idea_votes WHERE idea_id=' . $iid)->fetchColumn();
            out(['ok' => true, 'voted' => $voted, 'votes' => $n]);
        }

        if ($action === 'status') {
            if (!$isManager) out(['error' => 'Forbidden'], 403);
            $iid = (int)($in['idea_id'] ?? 0);
            $st  = $in['status'] ?? '';
            if (!$iid || !in_array($st, ['open','planned','in_progress','done','declined'], true))
                out(['error' => 'bad status'], 422);
            $pdo->prepare('UPDATE ideas SET status = ? WHERE id = ?')->execute([$st, $iid]);
            asf_audit('idea.status', "idea=$iid status=$st");
            $author = (int)$pdo->query('SELECT user_id FROM ideas WHERE id=' . $iid)->fetchColumn();
            if ($author && $author !== $uid) {
                asf_notify([$author], 'Your post was updated', 'Status changed to ' . str_replace('_',' ',$st),
                           'ideas.php?id=' . $iid, 'info');
            }
            out(['ok' => true]);
        }

        if ($action === 'delete') {
            $iid = (int)($in['idea_id'] ?? 0);
            if (!$iid) out(['error' => 'idea_id required'], 422);
            $author = (int)$pdo->query('SELECT user_id FROM ideas WHERE id=' . $iid)->fetchColumn();
            if (!$isManager && $author !== $uid) out(['error' => 'Forbidden'], 403);
            $pdo->prepare('DELETE FROM ideas WHERE id = ?')->execute([$iid]);
            asf_audit('idea.delete', "idea=$iid");
            out(['ok' => true]);
        }

        out(['error' => 'unknown action'], 400);
    }

    // ── GET ────────────────────────────────────────────────────────────
    if (!empty($_GET['id']) && ctype_digit((string)$_GET['id'])) {
        $iid  = (int)$_GET['id'];
        $idea = $pdo->prepare(
            'SELECT i.*, u.full_name AS author,
                    (SELECT COUNT(*) FROM idea_votes v WHERE v.idea_id = i.id) AS votes,
                    (SELECT COUNT(*) FROM idea_votes v WHERE v.idea_id = i.id AND v.user_id = ?) AS my_vote
               FROM ideas i LEFT JOIN users u ON u.id = i.user_id WHERE i.id = ?'
        );
        $idea->execute([$uid, $iid]);
        $row = $idea->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['error' => 'not found'], 404);
        $cstmt = $pdo->prepare(
            'SELECT c.id, c.body, c.created_at, u.full_name AS author
               FROM idea_comments c LEFT JOIN users u ON u.id = c.user_id
              WHERE c.idea_id = ? ORDER BY c.created_at ASC'
        );
        $cstmt->execute([$iid]);
        out(['idea' => $row, 'comments' => $cstmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $filter = $_GET['status'] ?? '';
    $where  = in_array($filter, ['open','planned','in_progress','done','declined'], true)
                ? 'WHERE i.status = ' . $pdo->quote($filter) : '';
    $sort   = ($_GET['sort'] ?? '') === 'new' ? 'i.created_at DESC' : 'votes DESC, i.created_at DESC';
    $stmt = $pdo->prepare(
        "SELECT i.id, i.title, i.body, i.category, i.status, i.created_at,
                i.user_id AS author_id, u.full_name AS author,
                (SELECT COUNT(*) FROM idea_votes v WHERE v.idea_id = i.id) AS votes,
                (SELECT COUNT(*) FROM idea_comments c WHERE c.idea_id = i.id) AS comments,
                (SELECT COUNT(*) FROM idea_votes v WHERE v.idea_id = i.id AND v.user_id = ?) AS my_vote
           FROM ideas i LEFT JOIN users u ON u.id = i.user_id
           $where ORDER BY $sort LIMIT 200"
    );
    $stmt->execute([$uid]);
    out(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} catch (Throwable $e) {
    out(['error' => 'server', 'detail' => $e->getMessage()], 500);
}

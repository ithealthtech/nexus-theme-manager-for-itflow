<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";


// Ticket status from GET
if (!isset($_GET['status']) || ($_GET['status']) == 'Open') {
    // Default to showing open
    $status = 'Open';
    $ticket_status_snippet = "ticket_closed_at IS NULL";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
    $status = 'Closed';
    $ticket_status_snippet = "ticket_closed_at IS NOT NULL";
} else {
    $status = '%';
    $ticket_status_snippet = "ticket_status LIKE '%'";
}

$contact_tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status_name FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id WHERE $ticket_status_snippet AND ticket_contact_id = $session_contact_id AND ticket_client_id = $session_client_id ORDER BY ticket_id DESC");

//Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_closed_at IS NOT NULL AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_assoc($sql_total_tickets_closed);
$total_tickets_closed = intval($row['total_tickets_closed']);

//Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_closed_at IS NULL AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_assoc($sql_total_tickets_open);
$total_tickets_open = intval($row['total_tickets_open']);

//Get Total tickets
$sql_total_tickets = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets FROM tickets WHERE ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_assoc($sql_total_tickets);
$total_tickets = intval($row['total_tickets']);


?>

<?php if ($nexus_theme_enabled ?? false) { ?>
<div class="nexus-ticket-title">
    <div>
        <span class="nexus-eyebrow">Support history</span>
        <h1 class="h2 mb-1">Your support requests</h1>
        <p class="text-muted mb-0">Review open requests and recent updates from our team.</p>
    </div>
    <a href="ticket_add.php" class="btn nexus-portal-cta"><i class="fas fa-plus me-2" aria-hidden="true"></i>Create support request</a>
</div>
<?php } else { ?>
<h3>Tickets</h3>
<?php } ?>
<div class="row">

    <div class="col-md-10">

        <?php if (mysqli_num_rows($contact_tickets) == 0) { ?>
            <?= portalEmptyState($status === '%' ? 'You have no tickets.' : 'You have no ' . strtolower($status) . ' tickets.') ?>
        <?php } else { ?>
        <div class="<?= ($nexus_theme_enabled ?? false) ? 'table-responsive' : '' ?>">
        <table class="table table-bordered border border-dark <?= ($nexus_theme_enabled ?? false) ? 'table-hover mb-0' : '' ?>">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_assoc($contact_tickets)) {
                $ticket_id = intval($row['ticket_id']);
                $ticket_prefix = escapeHtml($row['ticket_prefix']);
                $ticket_number = intval($row['ticket_number']);
                $ticket_subject = escapeHtml($row['ticket_subject']);
                $ticket_status = escapeHtml($row['ticket_status_name']);
            ?>

                <tr>
                    <td>
                        <a href="ticket.php?id=<?= $ticket_id ?>"><?= "$ticket_prefix$ticket_number" ?></a>
                    </td>
                    <td>
                        <a href="ticket.php?id=<?= $ticket_id ?>"><?= $ticket_subject ?></a>
                    </td>
                    <td><?php if ($nexus_theme_enabled ?? false) { ?><span class="badge text-bg-light px-2 py-1"><?= $ticket_status ?></span><?php } else { ?><?= $ticket_status ?><?php } ?></td>
                </tr>
            <?php
            }
            ?>
            </tbody>
        </table>
        </div>
        <?php } ?>

    </div>

    <div class="col-md-2">

        <?php /* Nexus moves the create action into the page hero above, so the
                 sidebar starts on the status filters. */ ?>
        <?php if (!($nexus_theme_enabled ?? false)) { ?>
        <a href="ticket_add.php" class="btn btn-primary w-100">New ticket</a>

        <hr>
        <?php } ?>

        <a href="?status=Open" class="btn btn-danger w-100 p-3 mb-3 text-start">My Open tickets | <strong><?= $total_tickets_open ?></strong></a>

        <a href="?status=Closed" class="btn btn-success w-100 p-3 mb-3 text-start">Closed tickets | <strong><?= $total_tickets_closed ?></strong></a>

        <a href="?status=%" class="btn btn-secondary w-100 p-3 mb-3 text-start">All my tickets | <strong><?= $total_tickets ?></strong></a>
        <?php
        if ($session_contact_primary == 1 || $session_contact_is_technical_contact) {
        ?>

        <hr>

        <a href="ticket_view_all.php" class="btn btn-dark w-100 p-2 mb-3">All Tickets</a>

        <?php
        }
        ?>

    </div>
</div>

<?php require_once "includes/footer.php";
 ?>

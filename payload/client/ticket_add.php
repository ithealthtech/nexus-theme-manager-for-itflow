<?php
/*
 * Client Portal
 * New ticket form
 */

require_once 'includes/inc_all.php';

// Allow clients to select a related asset when raising a ticket
$sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type FROM assets WHERE asset_contact_id = $session_contact_id AND asset_client_id = $session_client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="index.php">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="tickets.php">Tickets</a>
        </li>
        <li class="breadcrumb-item active">New Ticket</li>
    </ol>

    <span class="itdr-eyebrow">Tell us what is happening</span>
    <h1 class="h2">Create a support request</h1>
    <p class="text-muted mb-4">Share the impact and relevant details so we can route your request quickly.</p>

    <div class="col-md-8">
        <div class="card">
        <div class="card-body">
        <form action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label for="ticket-subject">Subject <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    </div>
                    <input type="text" class="form-control" id="ticket-subject" name="subject" placeholder="Briefly describe the issue" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ticket-priority">Priority <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                            </div>
                            <select class="form-control select2" id="ticket-priority" name="priority" required>
                                <option>Low</option>
                                <option>Medium</option>
                                <option>High</option>
                                <option>Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                    <label for="ticket-category">Category</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        </div>
                        <select class="form-control select2" id="ticket-category" name="category">
                            <option value="0">- No Category -</option>
                            <?php
                            $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                            while ($row = mysqli_fetch_assoc($sql_categories)) {
                                $category_id = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);

                                ?>
                                <option value="<?= $category_id ?>"><?= $category_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
                </div>
            </div>

            <?php if (mysqli_num_rows($sql_assets) > 0) { ?>
                <div class="form-group">
                    <label for="ticket-asset">Affected device</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        </div>
                        <select class="form-control select2" id="ticket-asset" name="asset">
                            <option value="0">- None -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_name = escapeSql($row['asset_name']);
                                $asset_type = escapeSql($row['asset_type']);
                                ?>
                                <option value="<?= $asset_id ?>"><?= "$asset_name ($asset_type)" ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            <?php } ?>


            <div class="form-group">
                <label for="ticket-details">Details <strong class="text-danger">*</strong></label>
                <p class="form-text mt-0" id="ticket-details-help">Include what you expected, what happened, who is affected, and any error message.</p>
                <textarea class="form-control tinymce" id="ticket-details" name="details" aria-describedby="ticket-details-help"></textarea>
            </div>

            <button class="btn btn-primary" name="add_ticket"><i class="fas fa-paper-plane mr-2" aria-hidden="true"></i>Submit support request</button>
            <a class="btn btn-outline-secondary ml-2" href="tickets.php">Cancel</a>

        </form>
        </div>
        </div>
    </div>

<?php
require_once 'includes/footer.php';

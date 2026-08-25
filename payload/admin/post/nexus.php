<?php

defined('FROM_POST_HANDLER') || die('Direct file access is not allowed');

$nexusRedirect = static function (): never {
    $allowedSections = ['brand', 'colors', 'layout', 'quality', 'motion', 'content', 'operations', 'system'];
    $returnSection = (string)($_POST['nexus_return_section'] ?? '');
    $fragment = in_array($returnSection, $allowedSections, true) ? '#nexus-' . $returnSection : '';
    header('Location: /admin/nexus.php' . $fragment);
    exit;
};

if (isset($_POST['nexus_diagnostics_download'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    $diagnostics = nexusThemeDiagnostics();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexus-theme-diagnostics-' . gmdate('Ymd-His') . '.json"');
    header('Cache-Control: no-store');
    echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit;
}

if (isset($_POST['nexus_quality_fix'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $width = max(320, min(1920, (int)($_POST['nexus_quality_width'] ?? 390)));
        $current = nexusThemeDraftSettings() ?? nexusThemeSettings();
        $fixed = nexusThemeApplyQualityFixes($current, $width);
        nexusThemeSaveDraftSettings($fixed, null, (string)($_POST['nexus_draft_version'] ?? 'none'));
        logAudit('Nexus Theme Manager', 'Edit', "$session_name applied safe Nexus quality corrections to the draft");
        flashAlert('Safe accessibility and responsive corrections saved to the private draft. Review every preview before publishing.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not apply quality corrections: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_revision_pin'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $pinned = (string)$_POST['nexus_revision_pin'] === '1';
        nexusThemePinRevision((string)($_POST['nexus_revision_id'] ?? ''), $pinned);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name " . ($pinned ? 'pinned' : 'unpinned') . ' a Nexus known-good revision');
        flashAlert($pinned ? 'Revision protected as a known-good design.' : 'Known-good protection removed from the revision.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not update revision protection: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_state'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    $requestedState = (string)$_POST['nexus_theme_state'];
    if (!in_array($requestedState, ['enable', 'disable'], true)) {
        flashAlert('Invalid Nexus theme action.', 'error');
        $nexusRedirect();
    }

    $enabled = $requestedState === 'enable';
    try {
        nexusThemeSetEnabled($enabled);
        $stateLabel = $enabled ? 'enabled' : 'paused';
        logAudit('Nexus Theme Manager', 'Edit', "$session_name $stateLabel the Nexus theme");
        flashAlert('Nexus theme ' . $stateLabel . '.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not update the theme state: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_save'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $current = nexusThemeDraftSettings() ?? nexusThemeSettings();
        $submitted = is_array($_POST['nexus'] ?? null) ? $_POST['nexus'] : [];
        $submitted['branding'] = is_array($submitted['branding'] ?? null) ? $submitted['branding'] : [];
        foreach (['logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path', 'asset_revision'] as $asset) {
            $submitted['branding'][$asset] = $current['branding'][$asset];
        }

        $assetChanged = false;
        foreach (['nexus_logo_light' => ['logo_light_path', 'logo-light'], 'nexus_logo_dark' => ['logo_dark_path', 'logo-dark'], 'nexus_favicon' => ['favicon_path', 'favicon'], 'nexus_login_background' => ['login_background_path', 'login-background']] as $uploadName => [$setting, $slot]) {
            if (isset($_FILES[$uploadName]) && ($_FILES[$uploadName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $submitted['branding'][$setting] = nexusThemeStoreImage($_FILES[$uploadName], $slot);
                $assetChanged = true;
            }
        }
        if ($assetChanged) {
            $submitted['branding']['asset_revision'] = bin2hex(random_bytes(8));
        }
        $submitted['branding']['logo_path'] = $submitted['branding']['logo_light_path'];

        nexusThemeSaveDraftSettings($submitted, null, (string)($_POST['nexus_draft_version'] ?? 'none'));
        logAudit('Nexus Theme Manager', 'Edit', "$session_name saved a Nexus design draft");
        flashAlert('Nexus draft saved. The published design is unchanged until you publish.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not save the draft: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_remove_asset'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $slot = (string)$_POST['nexus_theme_remove_asset'];
        $settingMap = ['logo-light' => 'logo_light_path', 'logo-dark' => 'logo_dark_path', 'favicon' => 'favicon_path', 'login-background' => 'login_background_path'];
        if (!isset($settingMap[$slot])) {
            throw new RuntimeException('Invalid Nexus asset action.');
        }
        $settings = nexusThemeDraftSettings() ?? nexusThemeSettings();
        $settings['branding'][$settingMap[$slot]] = '';
        if ($slot === 'logo-light') {
            $settings['branding']['logo_path'] = '';
        }
        $settings['branding']['asset_revision'] = bin2hex(random_bytes(8));
        nexusThemeSaveDraftSettings($settings, null, (string)($_POST['nexus_draft_version'] ?? 'none'));
        logAudit('Nexus Theme Manager', 'Edit', "$session_name detached an image in the Nexus draft");
        flashAlert('Custom image detached from the draft. Publish when the preview is ready.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not remove the image: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_draft_action'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $action = (string)$_POST['nexus_theme_draft_action'];
        if ($action === 'publish') {
            nexusThemePublishDraft((string)$session_name, null, (string)($_POST['nexus_revision_name'] ?? 'Published draft'), (string)($_POST['nexus_draft_version'] ?? 'none'));
            logAudit('Nexus Theme Manager', 'Edit', "$session_name published a Nexus design draft");
            flashAlert('Nexus draft published atomically across live surfaces.');
        } elseif ($action === 'discard') {
            nexusThemeDiscardDraft(null, (string)($_POST['nexus_draft_version'] ?? 'none'));
            logAudit('Nexus Theme Manager', 'Edit', "$session_name discarded a Nexus design draft");
            flashAlert('Unpublished Nexus changes discarded.');
        } elseif ($action === 'restore') {
            nexusThemeRestoreRevisionToDraft((string)($_POST['nexus_revision_id'] ?? ''), null, (string)($_POST['nexus_draft_version'] ?? 'none'));
            logAudit('Nexus Theme Manager', 'Edit', "$session_name restored a Nexus revision into the draft workspace");
            flashAlert('Revision restored as an unpublished draft. Review its exact previews, then publish when ready.');
        } else {
            throw new RuntimeException('Invalid Nexus draft action.');
        }
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not complete the draft action: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_preset_action'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $action = (string)$_POST['nexus_preset_action'];
        $id = (string)($_POST['nexus_preset_id'] ?? '');
        if ($action === 'save') {
            nexusThemeSavePreset((string)($_POST['nexus_preset_name'] ?? ''), nexusThemeDraftSettings() ?? nexusThemeSettings());
            $message = 'Theme preset saved.';
        } elseif ($action === 'apply' && preg_match('/^[a-f0-9]{16}$/', $id) === 1) {
            nexusThemeApplyPreset($id, null, (string)($_POST['nexus_draft_version'] ?? 'none'));
            $message = 'Theme preset loaded into the draft workspace.';
        } elseif ($action === 'delete' && preg_match('/^[a-f0-9]{16}$/', $id) === 1) {
            nexusThemeDeletePreset($id);
            $message = 'Theme preset deleted.';
        } elseif ($action === 'import') {
            nexusThemeSnapshotActive((string)$session_name, 'Automatic snapshot before preset import');
            $count = nexusThemeImportPresets((string)($_POST['nexus_presets_json'] ?? ''));
            $message = $count . ' theme preset' . ($count === 1 ? '' : 's') . ' imported.';
        } else {
            throw new RuntimeException('Invalid saved-preset action.');
        }
        logAudit('Nexus Theme Manager', 'Edit', "$session_name used Nexus saved presets");
        flashAlert($message);
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not complete the preset action: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_schedule_command'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $command = (string)$_POST['nexus_schedule_command'];
        if ($command === 'cancel') {
            nexusThemeCancelSchedule();
            $message = 'Scheduled theme action cancelled.';
        } elseif ($command === 'set') {
            nexusThemeSnapshotActive((string)$session_name, 'Automatic snapshot before scheduled activation');
            $schedule = nexusThemeSetSchedule((string)($_POST['nexus_schedule_action'] ?? ''), (string)($_POST['nexus_schedule_at'] ?? ''));
            $message = 'Theme ' . ($schedule['action'] === 'enable' ? 'activation' : 'pause') . ' scheduled for ' . $schedule['activate_at'] . '.';
        } else {
            throw new RuntimeException('Invalid schedule action.');
        }
        logAudit('Nexus Theme Manager', 'Edit', "$session_name updated the Nexus activation schedule");
        flashAlert($message);
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not update the schedule: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_reset'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        nexusThemeSaveDraftSettings(nexusThemeDefaults(), null, (string)($_POST['nexus_draft_version'] ?? 'none'));
        logAudit('Nexus Theme Manager', 'Edit', "$session_name loaded Nexus defaults into the draft workspace");
        flashAlert('Nexus defaults loaded as a draft. The published design is unchanged.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not restore defaults: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_update_action'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $action = (string)$_POST['nexus_update_action'];
        if ($action === 'update') {
            nexusThemeSnapshotActive((string)$session_name, 'Automatic snapshot before Nexus update', null, true);
        }
        nexusUpdaterQueue($action);
        $label = $action === 'update' ? 'installation' : 'update check';
        logAudit('Nexus Theme Manager', 'Edit', "$session_name queued a protected Nexus $label");
        flashAlert('Nexus ' . $label . ' queued. This page will show progress when the protected updater starts.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not queue that updater action: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_import'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $decoded = json_decode((string)($_POST['nexus_import_json'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('The imported configuration must be a JSON object.');
        }
        $current = nexusThemeDraftSettings() ?? nexusThemeSettings();
        $decoded['branding'] = is_array($decoded['branding'] ?? null) ? $decoded['branding'] : [];
        foreach (['logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path', 'asset_revision'] as $asset) {
            $decoded['branding'][$asset] = $current['branding'][$asset];
        }
        nexusThemeSnapshotActive((string)$session_name, 'Automatic snapshot before configuration import');
        nexusThemeSaveDraftSettings($decoded, null, (string)($_POST['nexus_draft_version'] ?? 'none'));
        logAudit('Nexus Theme Manager', 'Edit', "$session_name imported a Nexus theme draft");
        flashAlert('Nexus configuration imported as a draft. Review and publish when ready.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not import that configuration: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

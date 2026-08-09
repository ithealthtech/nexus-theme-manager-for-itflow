<?php

defined('FROM_POST_HANDLER') || die('Direct file access is not allowed');

if (isset($_POST['nexus_theme_state'])) {
    validateCSRFToken();

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    $requestedState = (string)$_POST['nexus_theme_state'];
    if (!in_array($requestedState, ['enable', 'disable'], true)) {
        flashAlert('Invalid Nexus theme action.', 'error');
        header('Location: /admin/nexus.php');
        exit;
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

    header('Location: /admin/nexus.php');
    exit;
}

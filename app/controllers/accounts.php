<?php

declare(strict_types=1);

$access->requirePermission('manage_accounts');

if (is_post()) {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $token = trim((string) ($_POST['api_token'] ?? ''));
        if ($name === '' || $token === '') {
            flash('error', 'Name and API token are required.');
            redirect('r=accounts&action=create');
        }
        try {
            $client = new CloudflareClient($token);
            $client->verifyToken();
            $account = [
                'id' => uuid(),
                'name' => $name,
                'api_token' => $token,
                'status' => 'ok',
                'created_at' => now_iso(),
                'last_sync' => null,
                'last_error' => null,
            ];
            $store->update('accounts', function (array $accounts) use ($account): array {
                $accounts[] = $account;
                return $accounts;
            }, []);
            $sync->syncAccount($account);
            $logger->log('account.create', $access->user(), [
                'account_id' => $account['id'],
                'account_name' => $name,
            ]);
            flash('success', 'Cloudflare account added and synced.');
            redirect('r=accounts');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('r=accounts&action=create');
        }
    }

    if ($formAction === 'update') {
        $id = (string) ($_POST['id'] ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        $token = trim((string) ($_POST['api_token'] ?? ''));
        $account = $sync->findAccount($id);
        if (!$account) {
            flash('error', 'Account not found.');
            redirect('r=accounts');
        }
        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('r=accounts&action=edit&id=' . urlencode($id));
        }
        try {
            if ($token !== '') {
                $client = new CloudflareClient($token);
                $client->verifyToken();
            } else {
                $token = (string) $account['api_token'];
            }
            $store->update('accounts', function (array $accounts) use ($id, $name, $token): array {
                foreach ($accounts as &$acc) {
                    if (($acc['id'] ?? '') === $id) {
                        $acc['name'] = $name;
                        $acc['api_token'] = $token;
                    }
                }
                unset($acc);
                return $accounts;
            }, []);
            $updated = $sync->findAccount($id);
            if ($updated) {
                $sync->syncAccount($updated);
            }
            $logger->log('account.update', $access->user(), [
                'account_id' => $id,
                'account_name' => $name,
            ]);
            flash('success', 'Account updated.');
            redirect('r=accounts');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('r=accounts&action=edit&id=' . urlencode($id));
        }
    }

    if ($formAction === 'delete') {
        $id = (string) ($_POST['id'] ?? '');
        $account = $sync->findAccount($id);
        if (!$account) {
            flash('error', 'Account not found.');
            redirect('r=accounts');
        }
        $zoneIds = array_column($store->read('zones', [])[$id] ?? [], 'id');
        $store->update('accounts', function (array $accounts) use ($id): array {
            return array_values(array_filter($accounts, static fn(array $a): bool => ($a['id'] ?? '') !== $id));
        }, []);
        $store->update('zones', function (array $zones) use ($id): array {
            unset($zones[$id]);
            return $zones;
        }, []);
        $store->update('records', function (array $records) use ($zoneIds): array {
            foreach ($zoneIds as $zid) {
                unset($records[$zid]);
            }
            return $records;
        }, []);
        $store->update('meta', function (array $meta) use ($id): array {
            unset($meta[$id]);
            return $meta;
        }, []);
        $logger->log('account.delete', $access->user(), [
            'account_id' => $id,
            'account_name' => $account['name'] ?? '',
        ]);
        flash('success', 'Account removed.');
        redirect('r=accounts');
    }

    if ($formAction === 'sync') {
        if (!$access->canSyncAccount()) {
            http_response_code(403);
            exit('Forbidden.');
        }
        $id = (string) ($_POST['id'] ?? '');
        $account = $sync->findAccount($id);
        if (!$account) {
            flash('error', 'Account not found.');
            redirect('r=accounts');
        }
        $ok = $sync->syncAccount($account);
        $logger->log('account.sync', $access->user(), [
            'account_id' => $id,
            'success' => $ok,
        ]);
        flash($ok ? 'success' : 'error', $ok ? 'Account synced successfully.' : 'Sync failed. Check account status.');
        redirect('r=accounts');
    }
}

$action = $_GET['action'] ?? 'list';

if ($action === 'create') {
    render('accounts_form', [
        'title' => 'Add Cloudflare Account',
        'user' => $access->user(),
        'access' => $access,
        'account' => null,
        'active_nav' => 'accounts',
    ]);
    return;
}

if ($action === 'edit') {
    $id = (string) ($_GET['id'] ?? '');
    $account = $sync->findAccount($id);
    if (!$account) {
        flash('error', 'Account not found.');
        redirect('r=accounts');
    }
    render('accounts_form', [
        'title' => 'Edit Cloudflare Account',
        'user' => $access->user(),
        'access' => $access,
        'account' => $account,
        'active_nav' => 'accounts',
    ]);
    return;
}

$accounts = $store->read('accounts', []);
$meta = $store->read('meta', []);
$zonesByAccount = $store->read('zones', []);

render('accounts', [
    'title' => 'Cloudflare Accounts',
    'user' => $access->user(),
    'access' => $access,
    'accounts' => $accounts,
    'meta' => $meta,
    'zonesByAccount' => $zonesByAccount,
    'active_nav' => 'accounts',
]);

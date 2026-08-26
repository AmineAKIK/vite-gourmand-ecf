<?php

declare(strict_types=1);

function replaceOnce(string $path, string $old, string $new): void
{
    $text = file_get_contents($path);
    if ($text === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $count = substr_count($text, $old);
    if ($count !== 1) {
        throw new RuntimeException($path . ': expected exactly one occurrence, found ' . $count . ': ' . substr($old, 0, 120));
    }
    $written = file_put_contents($path, str_replace($old, $new, $text, $replaced));
    if ($written === false || $replaced !== 1) {
        throw new RuntimeException('Cannot write deterministic replacement: ' . $path);
    }
}

replaceOnce('src/Controllers/CommandeController.php', "use App\\Config\\ConfigurationIncompleteException;\nuse App\\Config\\Database;", "use App\\Config\\ConfigurationCompleteness;\nuse App\\Config\\ConfigurationIncompleteException;\nuse App\\Config\\Database;");
replaceOnce('src/Controllers/CommandeController.php', "use App\\Services\\OrderTransitionService;\nuse App\\Services\\PricingService;", "use App\\Services\\OrderTransitionService;\nuse App\\Services\\PaymentMethodRegistry;\nuse App\\Services\\PricingService;");
replaceOnce('src/Controllers/CommandeController.php', <<<'OLD'
        $modePaiement = sanitize($_POST['mode_paiement'] ?? 'virement');

        // Validate mode_paiement exists and is active
        $modeActif = db()->fetchOne(
            "SELECT code FROM mode_paiement WHERE code = ? AND actif = 1",
            [$modePaiement]
        );
        if (!$modeActif) {
            flash('error', 'Mode de paiement invalide.');
            redirect('/panier');
        }
OLD, <<<'NEW'
        $modePaiement = sanitize($_POST['mode_paiement'] ?? '');
        try {
            ConfigurationCompleteness::assertCheckoutReady();
            $paymentMethod = PaymentMethodRegistry::requireCheckoutMethod($modePaiement);
        } catch (ConfigurationIncompleteException $e) {
            error_log('[payment] checkout incomplet: ' . $e->getMessage());
            flash('error', 'Aucun moyen de paiement utilisable n’est actuellement configuré. Contactez le traiteur.');
            redirect('/panier');
        } catch (\InvalidArgumentException) {
            flash('error', 'Mode de paiement invalide ou indisponible.');
            redirect('/panier');
        }
NEW);
replaceOnce('src/Controllers/CommandeController.php', "            'currency'                    => \$pricing['currency'],\n            'prix_livraison_cents'        => \$pricing['prix_livraison_cents'],", "            'currency'                    => \$pricing['currency'],\n            'payment_method_code'         => (string) \$paymentMethod['code'],\n            'prix_livraison_cents'        => \$pricing['prix_livraison_cents'],");
replaceOnce('src/Controllers/CommandeController.php', "        if (\$modePaiement === 'cb_online') {", <<<'NEW'
        if ($paymentMethod['checkout_strategy'] === PaymentMethodRegistry::CHECKOUT_STRATEGY_PROVIDER_CONFIRMATION) {
            if (($paymentMethod['provider'] ?? null) !== 'stripe') {
                error_log('[payment] provider checkout non implémenté: ' . (string) ($paymentMethod['provider'] ?? 'none'));
                flash('error', 'Ce moyen de paiement en ligne n’est pas disponible.');
                redirect('/panier');
            }
NEW);

replaceOnce('src/Controllers/PanierController.php', "namespace App\\Controllers;\n\nuse App\\Models\\MenuModel;", "namespace App\\Controllers;\n\nuse App\\Config\\ConfigurationIncompleteException;\nuse App\\Models\\MenuModel;\nuse App\\Services\\PaymentMethodRegistry;");
replaceOnce('src/Controllers/PanierController.php', <<<'OLD'
        $panier = $_SESSION['panier'] ?? [];
        view('pages/panier/index', compact('panier'));
OLD, <<<'NEW'
        $panier = $_SESSION['panier'] ?? [];
        $paymentMethods = PaymentMethodRegistry::checkoutMethods();
        $paymentConfigurationError = null;
        try {
            PaymentMethodRegistry::assertCheckoutAvailable();
        } catch (ConfigurationIncompleteException $e) {
            $paymentConfigurationError = $e->getMessage();
        }

        view('pages/panier/index', compact('panier', 'paymentMethods', 'paymentConfigurationError'));
NEW);

replaceOnce('src/Views/pages/panier/index.php', <<<'OLD'
                        <div class="row g-2">
                            <?php $modesActifs = db()->fetchAll("SELECT * FROM mode_paiement WHERE actif = 1 ORDER BY mode_id"); ?>
                            <?php foreach ($modesActifs as $mode): ?>
                                <div class="col-12 col-sm-6">
                                    <label class="d-flex align-items-center gap-2 border rounded p-3">
                                        <input type="radio" name="mode_paiement" value="<?= sanitize($mode['code']) ?>" class="form-check-input mt-0" required <?= $mode['code'] === 'virement' ? 'checked' : '' ?>>
                                        <span class="small fw-medium"><?= sanitize($mode['libelle']) ?></span>
                                        <?php if ($mode['code'] === 'cb_online'): ?><i class="bi bi-shield-lock ms-auto text-success" title="Paiement sécurisé par Stripe"></i><?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
OLD, <<<'NEW'
                        <?php if (empty($paymentMethods)): ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                Aucun moyen de paiement n’est actuellement disponible. Contactez le traiteur avant de finaliser la commande.
                            </div>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($paymentMethods as $index => $mode): ?>
                                    <div class="col-12 col-sm-6">
                                        <label class="d-flex align-items-start gap-2 border rounded p-3 h-100">
                                            <input type="radio" name="mode_paiement" value="<?= sanitize($mode['code']) ?>" class="form-check-input mt-1" required <?= $index === 0 ? 'checked' : '' ?>>
                                            <span class="small">
                                                <span class="fw-medium d-block"><?= sanitize($mode['label']) ?></span>
                                                <?php if (!empty($mode['instructions'])): ?>
                                                    <span class="text-muted d-block mt-1"><?= sanitize($mode['instructions']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (($mode['provider'] ?? null) === 'stripe'): ?><i class="bi bi-shield-lock ms-auto text-success" title="Paiement sécurisé en ligne"></i><?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
NEW);
replaceOnce('src/Views/pages/panier/index.php', "const submitBtn = document.getElementById('btn-finaliser');\nconst totalBrutCents", "const submitBtn = document.getElementById('btn-finaliser');\nconst paymentInputs = Array.from(document.querySelectorAll('input[name=\"mode_paiement\"]'));\nconst totalBrutCents");
replaceOnce('src/Views/pages/panier/index.php', <<<'OLD'
    const date = dateInput ? dateInput.value.trim() : '';
    const heure = heureInput ? heureInput.value.trim() : '';
    if (submitBtn) submitBtn.disabled = !(livraisonOk && date && heure);
OLD, <<<'NEW'
    const date = dateInput ? dateInput.value.trim() : '';
    const heure = heureInput ? heureInput.value.trim() : '';
    const paymentSelected = paymentInputs.some(input => input.checked);
    if (submitBtn) submitBtn.disabled = !(livraisonOk && date && heure && paymentSelected);
NEW);
replaceOnce('src/Views/pages/panier/index.php', "heureInput && heureInput.addEventListener('change', checkForm);", "heureInput && heureInput.addEventListener('change', checkForm);\npaymentInputs.forEach(input => input.addEventListener('change', checkForm));");

replaceOnce('src/Models/CommandeModel.php', "*                adresse_livraison, ville_livraison, code_postal_livraison, prix_total_cents, prix_livraison_cents", "*                adresse_livraison, ville_livraison, code_postal_livraison, prix_total_cents, payment_method_code, prix_livraison_cents");
replaceOnce('src/Models/CommandeModel.php', <<<'OLD'
                    heure_livraison, adresse_livraison, ville_livraison, code_postal_livraison, prix_total_cents, currency, instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
OLD, <<<'NEW'
                    heure_livraison, adresse_livraison, ville_livraison, code_postal_livraison, prix_total_cents, currency, payment_method_code, instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
NEW);
replaceOnce('src/Models/CommandeModel.php', <<<'OLD'
                (int) $commandeData['prix_total_cents'],
                (string) $commandeData['currency'],
                $commandeData['instructions'] ?? null,
OLD, <<<'NEW'
                (int) $commandeData['prix_total_cents'],
                (string) $commandeData['currency'],
                (string) $commandeData['payment_method_code'],
                $commandeData['instructions'] ?? null,
NEW);

replaceOnce('src/Services/StripeWebhookFulfillmentService.php', <<<'OLD'
            [$commandeData, $pricing, $panier] = self::decodeSnapshots($draft);
            $commandeData['prix_total_cents'] = (int) $validated['amount_total'];

            $commandeId = self::createCommande($db, $commandeData, $pricing['lignes'] ?? []);
OLD, <<<'NEW'
            [$commandeData, $pricing, $panier] = self::decodeSnapshots($draft);
            $commandeData['prix_total_cents'] = (int) $validated['amount_total'];
            if (($commandeData['payment_method_code'] ?? '') !== 'cb_online') {
                throw new RuntimeException('Le draft Stripe ne porte pas le moyen de paiement CB attendu.');
            }

            $commandeId = self::createCommande($db, $commandeData, $pricing['lignes'] ?? []);
NEW);
replaceOnce('src/Services/StripeWebhookFulfillmentService.php', <<<'OLD'
                adresse_livraison, ville_livraison, code_postal_livraison,
                prix_total_cents, currency, instructions
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
OLD, <<<'NEW'
                adresse_livraison, ville_livraison, code_postal_livraison,
                prix_total_cents, currency, payment_method_code, instructions
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
NEW);
replaceOnce('src/Services/StripeWebhookFulfillmentService.php', <<<'OLD'
            (int) $commandeData['prix_total_cents'],
            (string) $commandeData['currency'],
            $commandeData['instructions'] ?? null,
OLD, <<<'NEW'
            (int) $commandeData['prix_total_cents'],
            (string) $commandeData['currency'],
            (string) $commandeData['payment_method_code'],
            $commandeData['instructions'] ?? null,
NEW);

replaceOnce('src/Controllers/StripeController.php', "namespace App\\Controllers;\n\nuse App\\Config\\OperatorConfiguration;", "namespace App\\Controllers;\n\nuse App\\Config\\ConfigurationIncompleteException;\nuse App\\Config\\OperatorConfiguration;");
replaceOnce('src/Controllers/StripeController.php', "use App\\Models\\PaymentAttemptModel;", "use App\\Models\\PaymentAttemptModel;\nuse App\\Services\\PaymentMethodRegistry;");
replaceOnce('src/Controllers/StripeController.php', <<<'OLD'
        requireAuth();

        $pending = $this->loadPendingPayment();
OLD, <<<'NEW'
        requireAuth();

        try {
            PaymentMethodRegistry::requireCheckoutMethod('cb_online');
        } catch (ConfigurationIncompleteException|\InvalidArgumentException $e) {
            error_log('[payment] checkout Stripe indisponible: ' . $e->getMessage());
            flash('error', 'Le paiement en ligne n’est plus disponible. Choisissez un autre moyen de paiement.');
            redirect('/panier');
        }

        $pending = $this->loadPendingPayment();
NEW);

replaceOnce('src/Controllers/Admin/ParametresController.php', "use App\\Config\\ConfigurationInvalidException;", "use App\\Config\\ConfigurationIncompleteException;\nuse App\\Config\\ConfigurationInvalidException;\nuse App\\Config\\Database;");
replaceOnce('src/Controllers/Admin/ParametresController.php', "use App\\Services\\MenuAdminService;\nuse App\\Services\\PricingService;", "use App\\Services\\MenuAdminService;\nuse App\\Services\\PaymentMethodRegistry;\nuse App\\Services\\PricingService;\nuse InvalidArgumentException;\nuse Throwable;");
replaceOnce('src/Controllers/Admin/ParametresController.php', <<<'OLD'
    public function index(): void
    {
        $storageKeys = self::tenantStorageKeys();
OLD, <<<'NEW'
    public function index(): void
    {
        if (($_GET['view'] ?? '') === 'payment-methods') {
            $paymentMethods = PaymentMethodRegistry::tenantPolicies();
            view('pages/admin/payment_methods', compact('paymentMethods'));
            return;
        }

        $storageKeys = self::tenantStorageKeys();
NEW);
replaceOnce('src/Controllers/Admin/ParametresController.php', <<<'OLD'
        $section = $this->postedSection();
        $allowedPostKeys = array_merge(self::tenantStorageKeys(), ['csrf_token', '_section']);
OLD, <<<'NEW'
        $section = $this->postedSection();
        if ($section === 'payment_methods') {
            $this->updatePaymentMethods();
            return;
        }

        $allowedPostKeys = array_merge(self::tenantStorageKeys(), ['csrf_token', '_section']);
NEW);
replaceOnce('src/Controllers/Admin/ParametresController.php', <<<'OLD'
    /** @return list<string> */
    private static function tenantStorageKeys(): array
OLD, <<<'NEW'
    private function updatePaymentMethods(): void
    {
        $posted = $_POST['payment_methods'] ?? [];
        if (!is_array($posted)) {
            flash('error', 'Configuration des moyens de paiement invalide.');
            redirect('/admin/parametres?view=payment-methods');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            foreach (array_keys(PaymentMethodRegistry::capabilities()) as $code) {
                $policy = $posted[$code] ?? [];
                if (!is_array($policy)) {
                    throw new InvalidArgumentException('Politique de paiement invalide : ' . $code);
                }

                PaymentMethodRegistry::saveTenantPolicy(
                    $code,
                    isset($policy['active']),
                    isset($policy['checkout_enabled']),
                    isset($policy['manual_collection_enabled']),
                    isset($policy['allow_deposit']),
                    isset($policy['allow_balance']),
                    isset($policy['allow_single_payment']),
                    is_string($policy['instructions'] ?? null) ? $policy['instructions'] : '',
                );
            }
            $db->commit();
        } catch (ConfigurationIncompleteException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('error', 'Configuration opérateur manquante : ' . implode(', ', $e->keys()) . '.');
            redirect('/admin/parametres?view=payment-methods');
        } catch (InvalidArgumentException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('error', $e->getMessage());
            redirect('/admin/parametres?view=payment-methods');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[payment] mise à jour registry impossible: ' . $e->getMessage());
            flash('error', 'Impossible de mettre à jour les moyens de paiement.');
            redirect('/admin/parametres?view=payment-methods');
        }

        flash('success', 'Moyens de paiement mis à jour.');
        redirect('/admin/parametres?view=payment-methods');
    }

    /** @return list<string> */
    private static function tenantStorageKeys(): array
NEW);
replaceOnce('src/Controllers/Admin/ParametresController.php', "['identite', 'entreprise', 'fiscal', 'paiement', 'tarification', 'legal', 'avance'],", "['identite', 'entreprise', 'fiscal', 'paiement', 'payment_methods', 'tarification', 'legal', 'avance'],");

replaceOnce('bin/verify-v1-schema.php', <<<'OLD'
    assertColumns($db, 'commande', [
        'prix_total_cents',
        'currency',
    ], [
OLD, <<<'NEW'
    assertColumns($db, 'commande', [
        'prix_total_cents',
        'currency',
        'payment_method_code',
    ], [
NEW);
replaceOnce('bin/verify-v1-schema.php', "    assertColumns(\$db, 'paiement', ['montant_cents'], ['montant']);\n\n    foreach (['v_paiements_commande'", <<<'NEW'
    assertColumns($db, 'paiement', ['montant_cents'], ['montant']);
    assertColumns($db, 'mode_paiement', [
        'actif',
        'checkout_enabled',
        'manual_collection_enabled',
        'allow_deposit',
        'allow_balance',
        'allow_single_payment',
        'instructions',
    ], []);

    foreach (['chk_mode_paiement_flags', 'chk_mode_paiement_cb_online', 'chk_mode_paiement_instructions'] as $constraint) {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
        );
        $stmt->execute(['mode_paiement', $constraint]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Contrainte moyens de paiement V1 manquante : ' . $constraint);
        }
    }

    foreach (['v_paiements_commande'
NEW);

replaceOnce('tests/Unit/Config/ConfigurationCompletenessContractTest.php', <<<'OLD'
    public function testCheckoutAddsOperatorStripeRequirements(): void
    {
        $checkout = ConfigurationCompleteness::keys('checkout');

        self::assertContains('operator.stripe.secret_key', $checkout);
        self::assertContains('operator.base_url', $checkout);
    }
OLD, <<<'NEW'
    public function testCheckoutRemainsProviderAgnostic(): void
    {
        $checkout = ConfigurationCompleteness::keys('checkout');

        self::assertContains('order.capacity.max_per_day', $checkout);
        self::assertNotContains('operator.stripe.secret_key', $checkout);
        self::assertNotContains('operator.stripe.webhook_secret', $checkout);
        self::assertNotContains('operator.base_url', $checkout);
    }
NEW);

@unlink(__FILE__);
@unlink('.github/workflows/temp-payment-registry-refactor.yml');

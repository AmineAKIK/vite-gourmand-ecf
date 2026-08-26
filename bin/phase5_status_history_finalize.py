from pathlib import Path
import re


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f'missing marker in {path}: {old[:120]!r}')
    p.write_text(text.replace(old, new, 1))


p = Path('src/Models/CommandeModel.php')
text = p.read_text()
text = text.replace(
    'use App\\Services\\InventoryLedgerService;\n',
    'use App\\Services\\InventoryLedgerService;\nuse App\\Services\\OrderStatusHistoryService;\n',
    1,
)
text = text.replace(
    "            self::addHistorique($commandeId, null, OrderStatus::initial(), 'Commande passée', $commandeData['utilisateur_id']);",
    "            OrderStatusHistoryService::append(\n                $db,\n                $commandeId,\n                null,\n                OrderStatus::initial(),\n                'Commande passée',\n                (int) $commandeData['utilisateur_id'],\n            );",
    1,
)
text, count = re.subn(
    r"\n    public static function updateStatut\(int \$id, string \$statut, \?string \$commentaire, int \$modifiePar\): void \{.*?\n    \}\n\n    public static function updateDetails",
    '\n    public static function updateDetails',
    text,
    count=1,
    flags=re.S,
)
if count != 1:
    raise SystemExit('unable to remove CommandeModel::updateStatut')
text, count = re.subn(
    r"\n    public static function cancel\(int \$id, string \$motif, string \$modeContact, int \$modifiePar\): void \{.*?\n    \}\n\n    public static function getHistorique",
    '\n    public static function getHistorique',
    text,
    count=1,
    flags=re.S,
)
if count != 1:
    raise SystemExit('unable to remove CommandeModel::cancel')
text, count = re.subn(
    r"\n    public static function addHistorique\(int \$commandeId, \?string \$ancien, string \$nouveau, \?string \$commentaire, \?int \$modifiePar\): void \{.*?\n    \}\n",
    '\n',
    text,
    count=1,
    flags=re.S,
)
if count != 1:
    raise SystemExit('unable to remove CommandeModel::addHistorique')
p.write_text(text)

p = Path('src/Services/OrderTransitionService.php')
text = p.read_text()
old = """            $history = $db->prepare(
                'INSERT INTO commande_historique
                    (commande_id, ancien_statut, nouveau_statut, commentaire, modifie_par)
                 VALUES (?, ?, ?, ?, ?)',
            );
            $history->execute([
                $commandeId,
                $ancienStatut,
                $nouveauStatut,
                $commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null,
                $modifiePar,
            ]);"""
new = """            OrderStatusHistoryService::append(
                $db,
                $commandeId,
                $ancienStatut,
                $nouveauStatut,
                $commentaire,
                $modifiePar,
            );"""
replace_once('src/Services/OrderTransitionService.php', old, new)

p = Path('src/Services/OrderCancellationService.php')
text = p.read_text()
pattern = re.compile(
    r"\n            \$history = \$db->prepare\(.*?INSERT INTO commande_historique.*?\n            \$history->execute\(\[.*?\n            \]\);",
    re.S,
)
replacement = """
            OrderStatusHistoryService::append(
                $db,
                $commandeId,
                $oldStatus,
                OrderStatus::cancelled(),
                'Annulation (' . $modeContact . ') : ' . $motif,
                $modifiePar,
            );"""
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('cancellation history block missing')
p.write_text(text)

replace_once(
    'src/Services/StripeWebhookFulfillmentService.php',
    """        CommandeModel::addHistorique(
            $commandeId,
            null,
            OrderStatus::initial(),
            'Commande payée et créée par webhook Stripe',
            null,
        );""",
    """        OrderStatusHistoryService::append(
            $db,
            $commandeId,
            null,
            OrderStatus::initial(),
            'Commande payée et créée par webhook Stripe',
            null,
        );""",
)

replace_once(
    'src/Models/UserModel.php',
    """    public static function deleteEmploye(int $id): void {
        $db = Database::getConnection();
        $db->prepare(\"UPDATE commande_historique SET modifie_par = NULL WHERE modifie_par = ?\")->execute([$id]);
        $db->prepare(\"DELETE FROM password_reset WHERE utilisateur_id = ?\")->execute([$id]);
        $db->prepare(\"DELETE FROM utilisateur WHERE utilisateur_id = ?\")->execute([$id]);
    }""",
    """    public static function deleteEmploye(int $id): void {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $db->prepare(\"DELETE FROM password_reset WHERE utilisateur_id = ?\")->execute([$id]);
            $history = $db->prepare(\"SELECT 1 FROM commande_historique WHERE modifie_par = ? LIMIT 1\");
            $history->execute([$id]);
            if ($history->fetchColumn() !== false) {
                $db->prepare(
                    \"UPDATE utilisateur
                     SET email = ?, password = '*', prenom = 'Compte', nom = 'supprimé',
                         telephone = NULL, adresse = NULL, ville = NULL, code_postal = NULL,
                         actif = 0, must_change_password = 0
                     WHERE utilisateur_id = ? AND role_id = ?\"
                )->execute(['employe-supprime-' . $id . '@supprime.invalid', $id, ROLE_ID_EMPLOYE]);
            } else {
                $db->prepare(\"DELETE FROM utilisateur WHERE utilisateur_id = ? AND role_id = ?\")
                    ->execute([$id, ROLE_ID_EMPLOYE]);
            }
            $db->commit();
        } catch (\\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }""",
)

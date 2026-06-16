# Exemples : Comment étendre l'architecture

## 📌 Cas d'usage : Ajouter une notification par email lors d'un virement

### Étape 1 : Créer le Subscriber

```php
<?php
// src/EventSubscriber/VirementNotificationEmailSubscriber.php

namespace App\EventSubscriber;

use App\Event\VirementEffectueEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class VirementNotificationEmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité basse (50) : après le logger (100) et stats (50)
        return [
            VirementEffectueEvent::NAME => ['onVirementEffectue', 40],
        ];
    }

    /**
     * Envoyer une notification email quand un virement est effectué
     */
    public function onVirementEffectue(VirementEffectueEvent $event): void
    {
        $debit = $event->getTransactionDebit();
        $credit = $event->getTransactionCredit();
        $montant = $event->getMontant();

        $compteSource = $debit->getCompteSource();
        $compteDestination = $credit->getCompteDestination();

        // Email au client source
        $emailSource = (new Email())
            ->from('noreply@banque.com')
            ->to($compteSource->getClient()->getEmail())
            ->subject('Confirmation : Virement de ' . $montant . '€')
            ->html($this->genererHtmlSource($montant, $compteDestination));

        $this->mailer->send($emailSource);

        // Email au client destination
        $emailDest = (new Email())
            ->from('noreply@banque.com')
            ->to($compteDestination->getClient()->getEmail())
            ->subject('Virement reçu : ' . $montant . '€')
            ->html($this->genererHtmlDestination($montant, $compteSource));

        $this->mailer->send($emailDest);
    }

    private function genererHtmlSource(float $montant, ...$compte): string
    {
        return <<<HTML
            <h2>Virement confirmer</h2>
            <p>Vous avez viré <strong>${montant}€</strong></p>
            <p>Vers le compte destinataire</p>
        HTML;
    }

    private function genererHtmlDestination(float $montant, ...$compte): string
    {
        return <<<HTML
            <h2>Virement reçu</h2>
            <p>Vous avez reçu <strong>${montant}€</strong></p>
            <p>Depuis le compte source</p>
        HTML;
    }
}
```

### ✅ Résultat

- **Zéro modification** du Service existant
- **Zéro modification** du Controller existant
- **Nouveau Subscriber** complètement découplé
- Si l'email échoue, le virement est déjà enregistré (transaction BD déjà commitée)

---

## 📌 Cas d'usage 2 : Ajouter un audit trail (traçabilité)

### Créer un Subscriber d'audit

```php
<?php
// src/EventSubscriber/AuditTrailSubscriber.php

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Event\DepotEffectueEvent;
use App\Event\RetraitEffectueEvent;
use App\Event\VirementEffectueEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditTrailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VirementEffectueEvent::NAME => ['onVirementEffectue', 10],  // Priorité basse
            DepotEffectueEvent::NAME => ['onDepotEffectue', 10],
            RetraitEffectueEvent::NAME => ['onRetraitEffectue', 10],
        ];
    }

    public function onVirementEffectue(VirementEffectueEvent $event): void
    {
        $this->creerAuditLog(
            'VIREMENT',
            'Virement de ' . $event->getMontant() . '€ effectué',
            $event->getTransactionDebit()->getId()
        );
    }

    public function onDepotEffectue(DepotEffectueEvent $event): void
    {
        $this->creerAuditLog(
            'DEPOT',
            'Dépôt de ' . $event->getMontant() . '€ effectué',
            $event->getTransaction()->getId()
        );
    }

    public function onRetraitEffectue(RetraitEffectueEvent $event): void
    {
        $this->creerAuditLog(
            'RETRAIT',
            'Retrait de ' . $event->getMontant() . '€ effectué',
            $event->getTransaction()->getId()
        );
    }

    private function creerAuditLog(string $action, string $description, int $transactionId): void
    {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setTransactionId($transactionId);
        $log->setTimestamp(new \DateTimeImmutable());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
```

### ✅ Avantage

L'audit trail est complètement **transparent** pour la logique métier. On peut l'activer/désactiver dans `config/services.yaml` :

```yaml
services:
    # Désactiver l'audit en production temporairement
    App\EventSubscriber\AuditTrailSubscriber:
        tags: [{ name: event_subscriber, enabled: false }]
```

---

## 📌 Cas d'usage 3 : Améliorer un Voter avec plus de contexte

### Voter plus avancé : TransactionVoter avec historique

```php
<?php
// src/Security/TransactionVoter.php (version améliorée)

namespace App\Security;

use App\Entity\Client;
use App\Entity\Transaction;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class TransactionVoter extends Voter
{
    public const VIEW = 'transaction_view';
    public const EDIT = 'transaction_edit';       // Impossible d'éditer une transaction validée
    public const CANCEL = 'transaction_cancel';   // Annuler avant délai

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::CANCEL])
            && $subject instanceof Transaction;
    }

    protected function voteOnAttribute(string $attribute, mixed $transaction, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        // Admin a tous les droits
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if (!$user instanceof Client) {
            return false;
        }

        return match($attribute) {
            self::VIEW => $this->peutVoir($transaction, $user),
            self::EDIT => $this->peutEditer($transaction, $user),
            self::CANCEL => $this->peutAnnuler($transaction, $user),
            default => false,
        };
    }

    private function peutVoir(Transaction $transaction, Client $user): bool
    {
        $source = $transaction->getCompteSource();
        $dest = $transaction->getCompteDestination();
        
        return ($source && $source->getClient() === $user) 
            || ($dest && $dest->getClient() === $user);
    }

    private function peutEditer(Transaction $transaction, Client $user): bool
    {
        // Impossible d'éditer une transaction déjà validée
        if ($transaction->getStatut() !== 'en attente') {
            return false;
        }

        return $this->peutVoir($transaction, $user);
    }

    private function peutAnnuler(Transaction $transaction, Client $user): bool
    {
        // Peut annuler uniquement dans les 24 heures
        $delai = new \DateTimeImmutable('-24 hours');
        if ($transaction->getDateTransaction() < $delai) {
            return false;
        }

        return ($transaction->getStatut() === 'en attente' || $transaction->getStatut() === 'succès')
            && $this->peutVoir($transaction, $user);
    }
}
```

### Utilisation dans le Controller

```php
// src/Controller/ClientController.php

public function cancelTransaction(Transaction $transaction, Request $request): Response
{
    // Vérifier l'autorisation avec le Voter améloré
    $this->denyAccessUnlessGranted(TransactionVoter::CANCEL, $transaction);

    if ($transaction->getStatut() === 'succès') {
        // Créer une transaction de remboursement
        // ...
    }

    return $this->redirectToRoute('app_client_transactions');
}
```

---

## 📌 Cas d'usage 4 : Subscriber pour les alertes de solde

```php
<?php
// src/EventSubscriber/AlerteSoldeSubscriber.php

namespace App\EventSubscriber;

use App\Event\DepotEffectueEvent;
use App\Event\RetraitEffectueEvent;
use App\Event\VirementEffectueEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class AlerteSoldeSubscriber implements EventSubscriberInterface
{
    private const SOLDE_MIN_ALERTE = 100.0;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RetraitEffectueEvent::NAME => ['verifierSolde', 5],  // Priorité très basse
            VirementEffectueEvent::NAME => ['verifierSoldeVirement', 5],
        ];
    }

    public function verifierSolde(RetraitEffectueEvent $event): void
    {
        $compte = $event->getTransaction()->getCompteSource();

        if ($compte->getSolde() < self::SOLDE_MIN_ALERTE) {
            $this->logger->warning('Solde bas', [
                'compte_id' => $compte->getId(),
                'solde' => $compte->getSolde(),
                'seuil' => self::SOLDE_MIN_ALERTE,
            ]);

            // Envoyer une notification SMS au client ?
            // $this->smsService->send($compte->getClient()->getPhone(), '...');
        }
    }

    public function verifierSoldeVirement(VirementEffectueEvent $event): void
    {
        $compte = $event->getTransactionDebit()->getCompteSource();
        $this->verifierSolde(new RetraitEffectueEvent($event->getTransactionDebit()));
    }
}
```

---

## 📌 Cas d'usage 5 : Changer de priorité des Subscribers

Les Subscribers s'exécutent dans cet ordre :

```
100 - TransactionLoggerSubscriber::onDepotEffectue      (logs)
50  - TransactionStatistiquesSubscriber::onDepotEffectue (stats)
40  - VirementNotificationEmailSubscriber::...           (email)
10  - AuditTrailSubscriber::onDepotEffectue             (audit)
5   - AlerteSoldeSubscriber::verifierSolde              (alertes)
```

### Pourquoi ?

1. **100** : Logger en premier (témoignage)
2. **50** : Stats (impact métier modéré)
3. **40** : Notifications (communication)
4. **10** : Audit trail (conformité)
5. **5** : Alertes (effets secondaires)

---

## 🧪 Tester les Subscribers

```php
<?php
// tests/EventSubscriber/VirementNotificationEmailSubscriberTest.php

use PHPUnit\Framework\TestCase;
use App\Event\VirementEffectueEvent;
use App\EventSubscriber\VirementNotificationEmailSubscriber;
use Symfony\Component\Mailer\MailerInterface;

class VirementNotificationEmailSubscriberTest extends TestCase
{
    public function testEmailSentOnVirementEffectue(): void
    {
        // Arrange
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))
            ->method('send');

        $subscriber = new VirementNotificationEmailSubscriber($mailer);
        $event = new VirementEffectueEvent($debit, $credit);

        // Act
        $subscriber->onVirementEffectue($event);

        // Assert
        $mailer->verify();
    }
}
```

---

## 🎓 Lessons pour la maintenance future

| Concept | Avantage | Exemple |
|---------|----------|---------|
| **Single Responsibility** | Chaque classe = 1 responsabilité | TransactionLoggerSubscriber = juste log |
| **Open/Closed Principle** | Ouvert à l'extension, fermé à la modification | Ajouter un Subscriber sans toucher CompteService |
| **Dependency Injection** | Dépendances claires et testables | Constructor avec tous les services |
| **Events Pattern** | Découplage fort | Service → Event → Subscribers écoutent |
| **Voter Pattern** | Autorisation centralisée | Logique d'accès dans un seul endroit |

---

## 📚 Ressources

- [Design Patterns in Symfony - Events](https://symfony.com/doc/current/reference/events/index.html)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Event-Driven Architecture](https://martinfowler.com/articles/201701-event-driven.html)

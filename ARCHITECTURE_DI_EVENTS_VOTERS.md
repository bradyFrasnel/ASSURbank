# Architecture DI/Services/Events - Documentation

## 📋 Vue d'ensemble

Ce document décrit l'architecture avancée du projet suite à l'intégration des **Events** et des **Voters** selon les standards du cours Symfony Avancé (3e année IT).

---

## 🏗️ Architecture Implémentée

### 1. **Dependency Injection & Services** ✅

#### Fichier : `config/services.yaml`
```yaml
_defaults:
    autowire: true      # Auto-injection des dépendances
    autoconfigure: true # Auto-configuration des services
```

**Services métier créés :**
- `src/Service/CompteService.php` - Gestion des dépôts et retraits
- `src/Service/VirementService.php` - Gestion des virements

**Injection dans les Controllers :**
```php
public function depotCompte(
    Compte $compte,
    CompteService $compteService,  // ← Injecté automatiquement
): Response { }
```

---

### 2. **Events System** 🎯

#### Events créés :

**`src/Event/VirementEffectueEvent.php`**
```php
class VirementEffectueEvent extends Event {
    public const NAME = 'virement.effectue';
    // Contient les 2 transactions (débit + crédit)
}
```

**`src/Event/DepotEffectueEvent.php`**
```php
class DepotEffectueEvent extends Event {
    public const NAME = 'depot.effectue';
    // Contient la transaction
}
```

**`src/Event/RetraitEffectueEvent.php`**
```php
class RetraitEffectueEvent extends Event {
    public const NAME = 'retrait.effectue';
    // Contient la transaction
}
```

#### Dispatcher des events dans les Services :

**`src/Service/CompteService.php`**
```php
class CompteService {
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $dispatcher,  // ← Injecté
    ) { }
    
    public function depot(Compte $compte, float $montant, string $libelle): Transaction {
        // ... logique ...
        $this->dispatcher->dispatch(new DepotEffectueEvent($transaction));
        return $transaction;
    }
}
```

---

### 3. **Event Subscribers** 📡

Les Subscribers écoutent les événements sans que les Services les connaissent (découplage).

#### `src/EventSubscriber/TransactionLoggerSubscriber.php`
```php
class TransactionLoggerSubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents(): array {
        return [
            VirementEffectueEvent::NAME => ['onVirementEffectue', 100],
            DepotEffectueEvent::NAME => ['onDepotEffectue', 100],
            RetraitEffectueEvent::NAME => ['onRetraitEffectue', 100],
        ];
    }
    
    public function onVirementEffectue(VirementEffectueEvent $event): void {
        $this->logger->info('Virement effectué', [
            'montant' => $event->getMontant(),
            // ...
        ]);
    }
}
```

**Utilité :** Enregistrer en logs sans modifier le service.

#### `src/EventSubscriber/TransactionStatistiquesSubscriber.php`
```php
class TransactionStatistiquesSubscriber implements EventSubscriberInterface {
    public function onVirementEffectue(VirementEffectueEvent $event): void {
        $this->stats['virements_total']++;
        $this->stats['virements_montant_total'] += $event->getMontant();
    }
}
```

**Utilité :** Mettre à jour les statistiques sans modifier le service.

---

### 4. **Voters (Autorisation fine)** 🔐

Les Voters permettent une **autorisation basée sur les objets**, pas juste sur les rôles.

#### `src/Security/CompteVoter.php`
```php
class CompteVoter extends Voter {
    public const VIEW = 'compte_view';
    public const EDIT = 'compte_edit';
    public const OPERATIONS = 'compte_operations';  // Dépôt, retrait, virement
    
    private function peutEffectuerOperations(Compte $compte, Client $user): bool {
        return $compte->getClient() === $user && $compte->getStatut() === 'actif';
    }
}
```

#### `src/Security/TransactionVoter.php`
```php
class TransactionVoter extends Voter {
    public const VIEW = 'transaction_view';
    
    private function voteOnAttribute(string $attribute, mixed $transaction, TokenInterface $token): bool {
        // Vérifier que la transaction concerne un compte de l'utilisateur
    }
}
```

#### Utilisation dans les Controllers :

**Avant (❌ répétitif):**
```php
public function depotCompte(Compte $compte, Request $request, CompteService $compteService): Response {
    $client = $this->getUser();
    if ($compte->getClient() !== $client) {
        throw $this->createAccessDeniedException('...');
    }
    // ...
}
```

**Après (✅ propre):**
```php
public function depotCompte(Compte $compte, Request $request, CompteService $compteService): Response {
    // Utiliser le Voter - une seule ligne
    $this->denyAccessUnlessGranted(CompteVoter::OPERATIONS, $compte);
    // ...
}
```

---

## 📂 Structure des fichiers

```
src/
├── Service/
│   ├── CompteService.php          ← Dispatcher d'événements
│   └── VirementService.php         ← Dispatcher d'événements
├── Event/
│   ├── VirementEffectueEvent.php   ← Événement métier
│   ├── DepotEffectueEvent.php      ← Événement métier
│   └── RetraitEffectueEvent.php    ← Événement métier
├── EventSubscriber/
│   ├── TransactionLoggerSubscriber.php      ← Écoute & log
│   └── TransactionStatistiquesSubscriber.php ← Écoute & stats
├── Security/
│   ├── CompteVoter.php            ← Autorisation sur Compte
│   └── TransactionVoter.php        ← Autorisation sur Transaction
└── Controller/
    └── ClientController.php        ← Utilise les Voters
```

---

## 🎯 Avantages de cette architecture

### 1. **Découplage & Maintenabilité**
- Les Services ne connaissent pas les Subscribers
- Ajouter une notification = créer un Subscriber, pas modifier le Service

### 2. **Extensibilité**
```php
// Ajouter une nouvelle fonctionnalité sans toucher au Service existant
class VirementNotificationSubscriber implements EventSubscriberInterface {
    public function onVirementEffectue(VirementEffectueEvent $event): void {
        // Envoyer un email, SMS, push notification, etc.
    }
}
```

### 3. **Testabilité**
```php
// Tester le service sans les subscribers
$service = new CompteService($entityManager, new NullEventDispatcher());

// Tester les subscribers indépendamment
$subscriber = new TransactionLoggerSubscriber($logger);
$subscriber->onDepotEffectue($event);
```

### 4. **Autorisation flexible**
```php
// Voter centralise la logique métier d'autorisation
$this->denyAccessUnlessGranted(CompteVoter::OPERATIONS, $compte);
// Plutôt que de répéter "si le compte appartient au user ET est actif"
```

---

## 🔄 Flux d'exécution - Exemple : Dépôt

```
1. Controller reçoit la requête
   └─> Vérifie l'autorisation (Voter)

2. Appelle CompteService->depot()
   └─> Crée la transaction
   └─> Sauvegarde en BD
   └─> Dispatcher l'événement DepotEffectueEvent

3. Symfony fait réagir les Subscribers
   ├─> TransactionLoggerSubscriber::onDepotEffectue()
   │   └─> Enregistre en log
   └─> TransactionStatistiquesSubscriber::onDepotEffectue()
       └─> Met à jour les stats

4. Retour au Controller
   └─> Affiche un flash message
```

---

## 📚 Concepts du cours respectés

| Concept | Implémenté | Fichiers |
|---------|-----------|----------|
| **DI Container** | ✅ | `config/services.yaml` |
| **Auto-wiring** | ✅ | Injection dans Services & Controllers |
| **Services métier** | ✅ | `Service/*.php` |
| **Events** | ✅ | `Event/*.php` + `EventSubscriber/*.php` |
| **EventSubscriber** | ✅ | `EventSubscriber/*.php` |
| **Voters** | ✅ | `Security/*Voter.php` |
| **Autorisation fine** | ✅ | `denyAccessUnlessGranted(ACTION, $object)` |

---

## 🚀 Prochaines étapes possibles

1. **Messenger** - Rendre les événements asynchrones avec une file de messages
2. **API Platform** - Exposer les ressources via une API REST
3. **JWT** - Authentification sans session pour les APIs
4. **Tests** - Ajouter des tests unitaires et fonctionnels

---

## 📖 Ressources

- [Doctrine Events - Symfony Docs](https://symfony.com/doc/current/reference/events/index.html)
- [Voters - Symfony Docs](https://symfony.com/doc/current/security/voters.html)
- [Service Container - Symfony Docs](https://symfony.com/doc/current/service_container.html)

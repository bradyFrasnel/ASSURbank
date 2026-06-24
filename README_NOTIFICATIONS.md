# 🎉 Système de Notifications - PRÊT À UTILISER

## ✅ Configuration Complète

Tout est en place et fonctionnel :

1. ✅ **Mailpit** : Actif sur ports 56413 (web) et 56414 (SMTP)
2. ✅ **Base de données** : PostgreSQL sur port 56415
3. ✅ **Messenger** : Tables créées, 0 messages en file
4. ✅ **Handler** : TransactionNotificationHandler implémenté
5. ✅ **Events** : TransactionMessengerSubscriber actif

## 🚀 Démarrage Rapide

### Une seule commande pour démarrer le worker :

```bash
php bin/console messenger:consume async -vv
```

**⚠️ Important** : Laissez ce terminal ouvert pendant vos tests !

## 🧪 Test Immédiat

1. **Lancer le worker** (commande ci-dessus)

2. **Ouvrir Mailpit** : http://localhost:56413

3. **Se connecter à l'application** et faire une transaction :
   - Dépôt de 100€
   - Retrait de 50€
   - Virement vers un autre compte

4. **Observer** :
   - Terminal du worker → `[OK] Message App\Message\TransactionNotificationMessage handled`
   - Mailpit → Email visible instantanément

## 📧 Format des Emails

```
De: notifications@assurbank.fr
À: [email du client]
Sujet: Notification de transaction - [Type]

Bonjour [Prénom] [Nom],

Une transaction de type [depot/retrait/virement] 
d'un montant de [X] € a été effectuée 
sur votre compte n° [XXXXXX] le [date].

Libellé : [description]

Merci pour votre confiance,
L'équipe ASSURbank
```

## 🔍 Commandes de Monitoring

```bash
# Statistiques en temps réel
php bin/console messenger:stats

# Voir les messages échoués
php bin/console messenger:failed:show

# Réessayer les échecs
php bin/console messenger:failed:retry

# Limite de temps (1 heure puis arrêt auto)
php bin/console messenger:consume async --time-limit=3600

# Limite de messages (10 puis arrêt)
php bin/console messenger:consume async --limit=10
```

## 📊 Architecture

```
┌─────────────────────────────────────────────────────┐
│  1. Client fait une transaction (dépôt/retrait)    │
└──────────────────┬──────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────────────────┐
│  2. Event dispatché : DepotEffectueEvent, etc.     │
└──────────────────┬──────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────────────────┐
│  3. TransactionMessengerSubscriber écoute          │
│     → Dispatch TransactionNotificationMessage      │
└──────────────────┬──────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────────────────┐
│  4. Message stocké en BDD (table messenger_messages)│
└──────────────────┬──────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────────────────┐
│  5. Worker consomme le message                      │
└──────────────────┬──────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────────────────┐
│  6. TransactionNotificationHandler                  │
│     → Récupère la transaction depuis BDD            │
│     → Construit l'email HTML                        │
│     → Envoie via MailerInterface                    │
└──────────────────┬──────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────────────────┐
│  7. Mailpit intercepte l'email (SMTP 56414)        │
│     → Visible sur http://localhost:56413            │
└─────────────────────────────────────────────────────┘
```

## 🎓 Points Pédagogiques (SEANCE 2)

### 1. Relations Complexes
- `Transaction` → `Compte` → `Client` (ManyToOne en cascade)
- Lazy loading par défaut
- Eager loading possible avec `fetch="EAGER"`

### 2. QueryBuilder
```php
$qb = $this->createQueryBuilder('t')
    ->andWhere('t.compteSource = :compte')
    ->setParameter('compte', $compte)
    ->orderBy('t.dateTransaction', 'DESC');
```

### 3. DQL (Doctrine Query Language)
```php
$query = $em->createQuery('
    SELECT t, c, cl 
    FROM App\Entity\Transaction t
    JOIN t.compteSource c
    JOIN c.client cl
    WHERE t.montant > :montant
');
```

### 4. Optimisation
- **Index** : Sur les clés étrangères automatiques
- **Lazy vs Eager** : Choisir selon le use-case
- **Async** : Pas de blocage pendant l'envoi d'email
- **Batch Processing** : Le worker peut traiter des lots

### 5. Messenger Component
- **Transport** : Doctrine (simple), Redis (performant), AMQP (robuste)
- **Retry Strategy** : 3 tentatives avec délai exponentiel
- **Failed Transport** : Les échecs sont isolés et réessayables

## 🛠️ Configuration Actuelle

| Paramètre | Valeur |
|-----------|--------|
| Transport | Doctrine (table messenger_messages) |
| Max Retries | 3 |
| Retry Multiplier | 2 (délai x2 à chaque échec) |
| Mailpit SMTP | localhost:56414 |
| Mailpit Web | http://localhost:56413 |
| Database | postgresql://127.0.0.1:56415 |

## 📝 Fichiers Clés

```
src/
├── Message/
│   └── TransactionNotificationMessage.php    # DTO simple avec transactionId
├── MessageHandler/
│   └── TransactionNotificationHandler.php    # Logique d'envoi d'email
└── EventSubscriber/
    └── TransactionMessengerSubscriber.php    # Écoute les events, dispatch le message

config/packages/
└── messenger.yaml                            # Configuration du transport

.env
└── MAILER_DSN=smtp://localhost:56414         # Configuration Mailpit
```

## 🎯 Checklist de Démo

- [ ] Lancer le worker : `php bin/console messenger:consume async -vv`
- [ ] Ouvrir Mailpit : http://localhost:56413
- [ ] Se connecter en tant que client
- [ ] Faire un dépôt de 100€
- [ ] Vérifier le log dans le terminal du worker
- [ ] Voir l'email dans Mailpit
- [ ] Montrer `messenger:stats`
- [ ] Expliquer le flow asynchrone

## 🚨 Troubleshooting

### Problème : Le worker ne démarre pas
```bash
# Solution : Vérifier la connexion DB
php bin/console doctrine:schema:validate
```

### Problème : Pas d'email dans Mailpit
```bash
# Vérifier que Mailpit tourne
docker ps | grep mailpit

# Vérifier les logs
docker logs assurbank-mailer-1
```

### Problème : Messages bloqués
```bash
# Voir les messages en échec
php bin/console messenger:failed:show

# Les réessayer
php bin/console messenger:failed:retry
```

### Problème : Port 56414 inaccessible
```bash
# Redémarrer le conteneur
docker-compose restart mailer
```

## 🎉 Vous êtes prêt !

Le système fonctionne de bout en bout :
- ✅ Events → Messenger → Queue → Worker → Email → Mailpit
- ✅ Toutes les transactions génèrent automatiquement des notifications
- ✅ Le système est asynchrone et ne bloque jamais l'utilisateur
- ✅ Les échecs sont gérés avec retry automatique

**Commande finale** : `php bin/console messenger:consume async -vv`

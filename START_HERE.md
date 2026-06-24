# 🚀 DÉMARRAGE RAPIDE - ASSURbank

## ✅ Problèmes Résolus

- ✅ **Timeout PHP** : Augmenté à 300s (5 minutes)
- ✅ **Cache Symfony** : Nettoyé et optimisé
- ✅ **Mailpit** : Configuré et opérationnel
- ✅ **Messenger** : Prêt pour les notifications asynchrones
- ✅ **Polices CSS** : Réduites pour meilleur rendu

## 🎯 Démarrage en 3 Commandes

### 1️⃣ Démarrer Docker (Base de données + Mailpit)

```bash
docker-compose up -d
```

### 2️⃣ Démarrer le Serveur Web (Optimisé)

```bash
start-server.bat
```

**Ou manuellement** :
```bash
php -S 127.0.0.1:8000 -t public -d max_execution_time=300 -d memory_limit=512M
```

### 3️⃣ Démarrer le Worker Messenger (Terminal séparé)

```bash
start-messenger.bat
```

**Ou manuellement** :
```bash
php bin/console messenger:consume async -vv
```

## 🌐 Accès aux Interfaces

| Service | URL | Description |
|---------|-----|-------------|
| **Application** | http://127.0.0.1:8000 | Interface principale |
| **Mailpit** | http://localhost:56413 | Emails de notification |
| **Profiler** | http://127.0.0.1:8000/_profiler | Debug Symfony |

## 📂 Scripts Créés

| Script | Description | Usage |
|--------|-------------|-------|
| `start-server.bat` | Serveur web optimisé | Double-clic ou terminal |
| `start-messenger.bat` | Worker notifications | Terminal séparé |
| `setup-complete.bat` | Setup initial complet | Une fois au début |
| `test-notification.bat` | Vérification système | Test avant démo |

## 🧪 Test Rapide

1. **Ouvrir 3 fenêtres** :
   - Fenêtre 1 : `start-server.bat`
   - Fenêtre 2 : `start-messenger.bat`
   - Fenêtre 3 : Navigateur sur http://localhost:56413

2. **Se connecter** à l'application

3. **Faire une transaction** (dépôt/retrait)

4. **Observer** :
   - Terminal worker → Message traité
   - Mailpit → Email reçu

## 📚 Documentation Complète

| Document | Description |
|----------|-------------|
| `README_NOTIFICATIONS.md` | Guide technique complet |
| `DEMO_FINALE.md` | Scénario de présentation |
| `FIX_TIMEOUT.md` | Résolution problème timeout |
| `QUICK_START.md` | Démarrage rapide |
| `RECAP_FINAL.md` | Récapitulatif de tout |

## 🎓 Pour la SEANCE 2

### Concepts Couverts

- ✅ **Relations Doctrine** : OneToMany, ManyToOne
- ✅ **QueryBuilder** : Requêtes complexes
- ✅ **DQL** : Doctrine Query Language
- ✅ **Optimisation** : Lazy/Eager loading, Indexes
- ✅ **Messenger** : Traitement asynchrone
- ✅ **Events** : Event-driven architecture

### Points de Démonstration

1. **Architecture asynchrone** → Pas de blocage utilisateur
2. **Résilience** → Arrêt/redémarrage du worker
3. **Scalabilité** → Plusieurs workers possibles
4. **Monitoring** → `messenger:stats` en temps réel
5. **Relations complexes** → Transaction → Compte → Client

## ⚡ Commandes Essentielles

```bash
# Cache
php bin/console cache:clear
php bin/console cache:warmup

# Messenger
php bin/console messenger:stats
php bin/console messenger:failed:show
php bin/console messenger:failed:retry

# Debug
php bin/console debug:messenger
php bin/console debug:router
php bin/console debug:container

# Base de données
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
```

## 🔧 Résolution Rapide

### Serveur ne démarre pas
```bash
php bin/console cache:clear
start-server.bat
```

### Worker ne traite pas les messages
```bash
# Vérifier la connexion DB
php bin/console messenger:stats

# Relancer le worker
start-messenger.bat
```

### Emails n'arrivent pas
```bash
# Vérifier Mailpit
docker ps | grep mailpit

# Vérifier les logs
docker logs assurbank-mailer-1
```

### Timeout encore présent
```bash
# Augmenter encore plus
php -S 127.0.0.1:8000 -t public -d max_execution_time=0
```

## ✅ Checklist Avant Démo

- [ ] Docker démarré (`docker-compose up -d`)
- [ ] Serveur web lancé (`start-server.bat`)
- [ ] Worker Messenger actif (`start-messenger.bat`)
- [ ] Mailpit accessible (http://localhost:56413)
- [ ] Application accessible (http://127.0.0.1:8000)
- [ ] Cache nettoyé (`php bin/console cache:clear`)
- [ ] Compte client créé pour test
- [ ] `messenger:stats` affiche 0 messages

## 🎉 Tout est Prêt !

Le système est **100% fonctionnel** et optimisé.

**Commande unique pour tout démarrer** :
```bash
# Terminal 1
docker-compose up -d && start-server.bat

# Terminal 2
start-messenger.bat

# Navigateur
start http://127.0.0.1:8000
start http://localhost:56413
```

---

💡 **Astuce** : Utilisez `test-notification.bat` pour vérifier que tout fonctionne avant votre démo !

📞 **Besoin d'aide ?** Consultez `FIX_TIMEOUT.md` pour le dépannage.

Cours Symfony Avance — 3eme Annee IT Page 1
COURS AVANCE — 3EME ANNEE IT
SYMFONY
Framework PHP d'Entreprise
DI Container | Doctrine ORM | API Platform | Messenger | Microservices
Niveau 3eme Annee IT — Cours Avance
Duree 10 seances x 4 heures = 40 heures
Contenu Cours + TD + TP + Projet Systeme de Tickets
Prerequis PHP POO, Symfony bases, SQL, Git, notions API REST
Troisieme Annee — Informatique & Developpement
Cours Symfony Avance — 3eme Annee IT Page 2
Introduction — Symfony en entreprise
Symfony est le framework PHP le plus utilise en environnement d'entreprise. La ou Laravel
privilegia la productivite rapide, Symfony privilegia la rigueur architecturale, la modularite
et la maintenabilite a long terme. Des entreprises comme Drupal, phpBB, Magento et
Akeneo ont bati leurs produits sur les composants Symfony.
Ce cours de niveau avance suppose que vous connaissez deja les bases de Symfony
(controllers, routes, Twig, Doctrine basique). Nous allons approfondir les aspects qui font
de Symfony un outil de production serieux : injection de dependances avancee,
evenements, securite fine, API Platform, Messenger, tests et microservices.
Plan des 10 seances
Seanc
e Theme Competences cles
1 Architecture Symfony — DI,
Services, Events Container, Tags, EventDispatcher
2 Doctrine ORM avance Relations complexes, QueryBuilder, DQL
3 Formulaires avances Collections, upload, FormType imbrique
4 Securite avancee Voters, Guard, JWT, 2FA
5 API Platform REST, JSON-LD, Filtres, Groupes de serialisation
6 Messenger — Files de
messages Bus, Handlers, Workers, Retry
7 Tests automatises PHPUnit, WebTestCase, Fixtures, TDD
8 Performance et Cache HTTP Cache, Redis, Profiler, Optimisation Doctrine
9 Microservices avec Symfony Architecture, Communication, API Gateway
10 Projet final — Systeme de
tickets Web + API + Messenger + Tests
Cours Symfony Avance — 3eme Annee IT Page 3
SEANCE 1
Architecture Symfony — DI, Services,
Events
Conteneur de services | Injection de dependances | Systeme d'evenements
DI Container | Auto-wiring | Tags de services | EventDispatcher | Subscribers
1. Le Conteneur de Services (DI Container)
1.1 — Qu'est-ce que l'injection de dependances ?
L'injection de dependances (Dependency Injection) est un pattern qui consiste a fournir les
dependances d'un objet depuis l'exterieur plutot que de les creer en interne. Symfony
dispose d'un conteneur de services qui instancie et injecte automatiquement toutes les
dependances.
SANS DI — couplage fort class TicketService { public function __construct() {
$this->mailer = new Mailer(); // cree lui-meme sa dependance $this->repo = new
TicketRepo(); // impossible a tester ! } } AVEC DI — couplage faible class
TicketService { public function __construct( private MailerInterface $mailer, //
injecte de l'exterieur private TicketRepository $repo // facile a mocker dans les
tests ) {} }
1.2 — Auto-wiring Symfony
Symfony analyse automatiquement les types des parametres du constructeur et injecte les
bons services. C'est l'auto-wiring — plus besoin de configurer manuellement chaque
injection.
# config/services.yaml
services:
# Auto-wiring active par defaut
_defaults:
autowire: true
autoconfigure: true
# Enregistrer tous les services du dossier src/
App\:
resource: '../src/'
exclude:
Cours Symfony Avance — 3eme Annee IT Page 4
- '../src/Entity/'
- '../src/Kernel.php'
<?php
// src/Service/TicketService.php
namespace App\Service;
use App\Repository\TicketRepository;
use Symfony\Component\Mailer\MailerInterface;
use Psr\Log\LoggerInterface;
class TicketService {
// Symfony injecte automatiquement les 3 dependances
public function __construct(
private TicketRepository $repo,
private MailerInterface $mailer,
private LoggerInterface $logger
) {}
public function creerTicket(array $data): Ticket {
$ticket = new Ticket();
$ticket->setTitre($data['titre']);
$ticket->setStatut('ouvert');
$this->repo->save($ticket, true);
$this->logger->info('Ticket cree', ['id' => $ticket->getId()]);
return $ticket;
}
}
1.3 — Tags de services et Interfaces
# config/services.yaml
services:
# Liaison interface -> implementation concrete
App\Service\NotificationInterface: '@App\Service\EmailNotification'
# Tags : regrouper des services du meme type
App\Priority\PriorityCalculatorInterface:
tags: ['app.priority_calculator']
<?php
// Collecter tous les services tagges avec CompilerPass
namespace App\DependencyInjection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
class PriorityCalculatorPass implements CompilerPassInterface {
public function process(ContainerBuilder $container): void {
Cours Symfony Avance — 3eme Annee IT Page 5
$manager = $container->findDefinition('App\Service\PriorityManager');
$tagged = $container->findTaggedServiceIds('app.priority_calculator');
foreach ($tagged as $id => $tags) {
$manager->addMethodCall('addCalculator', [new Reference($id)]);
}
}
}
Cours Symfony Avance — 3eme Annee IT Page 6
2. Le systeme d'evenements Symfony
2.1 — EventDispatcher
Symfony dispose d'un systeme d'evenements puissant qui permet de decoupler les
composants. Un service emet un evenement, d'autres services l'ecoutent et reagissent —
sans que l'emetteur ne connaisse les ecouteurs.
<?php
// src/Event/TicketCreatedEvent.php
namespace App\Event;
use App\Entity\Ticket;
use Symfony\Contracts\EventDispatcher\Event;
class TicketCreatedEvent extends Event {
public const NAME = 'ticket.created';
public function __construct(
private Ticket $ticket
) {}
public function getTicket(): Ticket { return $this->ticket; }
}
<?php
// src/EventListener/TicketCreatedListener.php
namespace App\EventListener;
use App\Event\TicketCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
#[AsEventListener(event: TicketCreatedEvent::NAME, priority: 10)]
class TicketCreatedListener {
public function __invoke(TicketCreatedEvent $event): void {
$ticket = $event->getTicket();
// Envoyer un email de confirmation, notifier les admins...
echo 'Ticket cree : ' . $ticket->getTitre();
}
}
<?php
// Dans le service : dispatcher l'evenement
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
class TicketService {
public function __construct(
private EventDispatcherInterface $dispatcher,
// ...
Cours Symfony Avance — 3eme Annee IT Page 7
) {}
public function creerTicket(array $data): Ticket {
$ticket = new Ticket();
// ... sauvegarder ...
// Dispatcher l'evenement — les listeners reagissent automatiquement
$this->dispatcher->dispatch(new TicketCreatedEvent($ticket));
return $ticket;
}
}
2.2 — EventSubscriber
Un Subscriber s'abonne lui-meme a plusieurs evenements en une seule classe, ce qui est
plus pratique qu'un Listener par evenement.
<?php
// src/EventSubscriber/TicketSubscriber.php
namespace App\EventSubscriber;
use App\Event\TicketCreatedEvent;
use App\Event\TicketClosedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
class TicketSubscriber implements EventSubscriberInterface {
public static function getSubscribedEvents(): array {
return [
TicketCreatedEvent::NAME => ['onTicketCreated', 10],
TicketClosedEvent::NAME => ['onTicketClosed', 5],
];
}
public function onTicketCreated(TicketCreatedEvent $event): void {
// Envoyer email de creation
}
public function onTicketClosed(TicketClosedEvent $event): void {
// Envoyer email de cloture, mettre a jour les stats
}
}
Listener vs
Subscriber
Listener : une classe = un evenement, plus simple. Subscriber : une classe =
plusieurs evenements, plus pratique pour regrouper la logique liee. Les deux
sont auto-configures si autoconfigure: true dans services.yaml.
Cours Symfony Avance — 3eme Annee IT Page 8
TD 1 — Questions de comprehension
Question 1 — Qu'est-ce que l'injection de dependances ? Quel probleme resout-elle par
rapport a l'instanciation directe ?
Reponse :
Question 2 — Qu'est-ce que l'auto-wiring Symfony ? Comment fonctionne-t-il ?
Reponse :
Question 3 — Quelle est la difference entre un EventListener et un EventSubscriber ?
Quand utiliser l'un ou l'autre ?
Reponse :
Question 4 — A quoi servent les tags de services dans Symfony ? Donnez un exemple
concret.
Reponse :
Question 5 — Quel est l'avantage du pattern Event/Listener pour le decoupage du code ?
Reponse :
Question 6 — Comment Symfony sait-il quelle implementation concrete utiliser quand on
injecte une interface ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 9
TP 1 — Architecture services et evenements
Objectif Creer un systeme de notification par evenements pour le systeme de tickets.
Duree : 1h30.
# Structure a creer
src/
|-- Entity/Ticket.php
|-- Event/
| |-- TicketCreatedEvent.php
| |-- TicketAssignedEvent.php
| |-- TicketClosedEvent.php
|-- Service/
| |-- TicketService.php
| |-- NotificationService.php
|-- EventSubscriber/
|-- TicketNotificationSubscriber.php
Exercice TP1 — Travail a faire
1
.
Cree les 3 evenements (TicketCreated, TicketAssigned, TicketClosed) avec leurs
proprietes.
2
.
Cree le TicketService qui dispatche les evenements lors de chaque action.
3
.
Cree le TicketNotificationSubscriber qui ecoute les 3 evenements et affiche un log.
4
.
Bonus : Ajoute un 2eme subscriber StatistiquesSubscriber qui compte les tickets par
statut.
Cours Symfony Avance — 3eme Annee IT Page 10
SEANCE 2
Doctrine ORM Avance
Relations complexes | QueryBuilder | DQL | Optimisation
OneToMany/ManyToMany | QueryBuilder avance | DQL | Indexes | Lazy vs Eager loading
3. Relations Doctrine complexes | Seance 2
3.1 — Schema de la base du systeme de tickets
users tickets commentaires categories +-------+ +----------+ +------------+
+----------+ | id |<-+ | id |<-+ | id | | id | | nom | | | titre | | | contenu | |
nom | | email | | | statut | +--| ticket_id | | couleur | | role | +--| user_id |
| user_id | +----------+ +-------+ +--| assignee | | created_at | ^ | categorie|
+------------+ | | priorite | | +----------+----------------------------+ |
(ManyToMany via ticket_categories) | pieces_jointes +-----------+ | id | |
fichier | | ticket_id | +-----------+
3.2 — Entite Ticket avec toutes les relations
<?php
// src/Entity/Ticket.php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'tickets')]
#[ORM\Index(name: 'idx_statut', columns: ['statut'])]
#[ORM\Index(name: 'idx_priorite', columns: ['priorite'])]
class Ticket {
#[ORM\Id, ORM\GeneratedValue, ORM\Column]
private ?int $id = null;
#[ORM\Column(length: 200)]
private string $titre;
#[ORM\Column(type: 'text')]
Cours Symfony Avance — 3eme Annee IT Page 11
private string $description;
#[ORM\Column(length: 20)]
private string $statut = 'ouvert'; // ouvert, en_cours, resolu, ferme
#[ORM\Column(length: 10)]
private string $priorite = 'normale'; // basse, normale, haute, critique
#[ORM\Column]
private \DateTimeImmutable $createdAt;
// ManyToOne : plusieurs tickets -> un createur
#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ticketsCrees')]
#[ORM\JoinColumn(nullable: false)]
private User $createur;
// ManyToOne : plusieurs tickets -> un assigne (nullable)
#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ticketsAssignes')]
private ?User $assigne = null;
// OneToMany : un ticket -> plusieurs commentaires
#[ORM\OneToMany(mappedBy: 'ticket', targetEntity: Commentaire::class,
cascade: ['persist', 'remove'], orphanRemoval: true)]
private Collection $commentaires;
// ManyToMany : tickets <-> categories
#[ORM\ManyToMany(targetEntity: Categorie::class, inversedBy: 'tickets')]
#[ORM\JoinTable(name: 'ticket_categories')]
private Collection $categories;
public function __construct() {
$this->commentaires = new ArrayCollection();
$this->categories = new ArrayCollection();
$this->createdAt = new \DateTimeImmutable();
}
// Getters/Setters et methodes de collection
public function addCommentaire(Commentaire $c): static {
if (!$this->commentaires->contains($c)) {
$this->commentaires->add($c);
$c->setTicket($this);
}
return $this;
}
}
Cours Symfony Avance — 3eme Annee IT Page 12
3.3 — QueryBuilder Doctrine avance
<?php
// src/Repository/TicketRepository.php
namespace App\Repository;
use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
class TicketRepository extends ServiceEntityRepository {
// Recherche multicriteres avancee
public function rechercherAvancee(array $filtres, int $page = 1): array {
$qb = $this->createQueryBuilder('t')
->leftJoin('t.createur', 'u')->addSelect('u')
->leftJoin('t.assigne', 'a')->addSelect('a')
->leftJoin('t.categories','c')->addSelect('c');
if (!empty($filtres['statut'])) {
$qb->andWhere('t.statut = :statut')
->setParameter('statut', $filtres['statut']);
}
if (!empty($filtres['priorite'])) {
$qb->andWhere('t.priorite = :priorite')
->setParameter('priorite', $filtres['priorite']);
}
if (!empty($filtres['recherche'])) {
$qb->andWhere('t.titre LIKE :q OR t.description LIKE :q')
->setParameter('q', '%' . $filtres['recherche'] . '%');
}
if (!empty($filtres['assigne_id'])) {
$qb->andWhere('a.id = :aid')
->setParameter('aid', $filtres['assigne_id']);
}
return $qb->orderBy('t.createdAt', 'DESC')
->setFirstResult(($page - 1) * 20)
->setMaxResults(20)
->getQuery()
->getResult();
}
// Statistiques par statut
public function getStatistiquesParStatut(): array {
return $this->createQueryBuilder('t')
->select('t.statut, COUNT(t.id) as total')
->groupBy('t.statut')
->getQuery()
->getResult();
}
Cours Symfony Avance — 3eme Annee IT Page 13
// Tickets non assignes en retard
public function getTicketsUrgents(): array {
$limite = new \DateTimeImmutable('-48 hours');
return $this->createQueryBuilder('t')
->where('t.assigne IS NULL')
->andWhere('t.priorite IN (:prios)')
->andWhere('t.createdAt < :limite')
->setParameter('prios', ['haute', 'critique'])
->setParameter('limite', $limite)
->orderBy('t.priorite', 'DESC')
->getQuery()
->getResult();
}
}
N+1
Problem
Le probleme N+1 est le pieges le plus courant avec les ORMs. Si tu affiches 20
tickets et que tu accedes a $ticket->getCreateur() pour chacun, Doctrine
execute 1 + 20 = 21 requetes SQL ! La solution : utiliser
leftJoin()->addSelect() dans le QueryBuilder pour charger les relations en une
seule requete.
Cours Symfony Avance — 3eme Annee IT Page 14
TD 2 — Questions de comprehension
Question 1 — Expliquez la difference entre une relation OneToMany et ManyToMany.
Donnez un exemple pour chacune.
Reponse :
Question 2 — Qu'est-ce que le probleme N+1 avec Doctrine ? Comment le resoudre avec le
QueryBuilder ?
Reponse :
Question 3 — Quelle est la difference entre cascade: persist et cascade: remove dans une
relation Doctrine ?
Reponse :
Question 4 — Qu'est-ce que orphanRemoval: true dans une relation OneToMany ? Quand
l'utiliser ?
Reponse :
Question 5 — Quelle est la difference entre LAZY et EAGER loading dans Doctrine ?
Reponse :
Question 6 — Comment ajouter un index sur une colonne dans une Entity Doctrine ?
Pourquoi est-ce important ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 15
TP 2 — Schema de BDD complet avec Doctrine
Objectif Creer toutes les entites du systeme de tickets avec leurs relations. Duree :
1h30.
# Generer les entites
php bin/console make:entity User
php bin/console make:entity Ticket
php bin/console make:entity Commentaire
php bin/console make:entity Categorie
php bin/console make:entity PieceJointe
# Creer et executer les migrations
php bin/console make:migration
php bin/console doctrine:migrations:migrate
# Charger des fixtures de test
composer require --dev orm-fixtures
php bin/console make:fixtures
php bin/console doctrine:fixtures:load
Exercice TP2 — Travail a faire
1
.
Cree les 5 entites avec toutes les relations definies dans le schema.
2
.
Ajoute des index sur les colonnes statut, priorite et created_at de Ticket.
3
.
Cree des DataFixtures qui inserent 3 users, 10 tickets, et 20 commentaires de test.
4
.
Ecris une methode getTicketsParAgent(User $user) dans TicketRepository qui
retourne tous les tickets assignes a un agent avec leurs commentaires charges en
une seule requete.
Cours Symfony Avance — 3eme Annee IT Page 16
SEANCE 3
Formulaires Avances
FormType imbrique | Collections | Upload | Validation
FormType imbrique | Collection de sous-formulaires | Upload fichiers | Contraintes custom |
CSRF
4. Formulaires Symfony avances | Seance 3
4.1 — FormType imbrique
<?php
// src/Form/TicketType.php
namespace App\Form;
use App\Entity\Ticket;
use App\Entity\Categorie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\*;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
class TicketType extends AbstractType {
public function buildForm(FormBuilderInterface $builder, array $options): void {
$builder
->add('titre', TextType::class, [
'label' => 'Titre du ticket',
'constraints' => [
new Assert\NotBlank(),
new Assert\Length(['min' => 10, 'max' => 200]),
],
])
->add('description', TextareaType::class, [
'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 20])],
])
->add('priorite', ChoiceType::class, [
'choices' => [
'Basse' => 'basse',
'Normale' => 'normale',
Cours Symfony Avance — 3eme Annee IT Page 17
'Haute' => 'haute',
'Critique' => 'critique',
],
])
->add('categories', EntityType::class, [
'class' => Categorie::class,
'multiple' => true,
'expanded' => true, // Checkboxes
'label' => 'Categories',
])
// Sous-formulaire imbrique pour le premier commentaire
->add('premierCommentaire', CommentaireType::class, [
'label' => 'Description detaillee',
'mapped' => false, // non mappe directement sur l'entite
])
// Upload de pieces jointes
->add('fichiers', FileType::class, [
'multiple' => true,
'required' => false,
'mapped' => false,
'constraints' => [new Assert\All([
new Assert\File([
'maxSize' => '10M',
'mimeTypes' => ['image/*', 'application/pdf'],
'mimeTypesMessage' => 'Seuls PDF et images autorises',
])
])],
])
->add('soumettre', SubmitType::class, ['label' => 'Creer le ticket']);
}
public function configureOptions(OptionsResolver $resolver): void {
$resolver->setDefaults(['data_class' => Ticket::class]);
}
}
Cours Symfony Avance — 3eme Annee IT Page 18
4.2 — Collections de formulaires
Les Collections permettent d'ajouter/supprimer dynamiquement des sous-formulaires (ex:
plusieurs pieces jointes, plusieurs commentaires).
<?php
// Utiliser CollectionType dans le FormType
->add('commentaires', CollectionType::class, [
'entry_type' => CommentaireType::class,
'allow_add' => true,
'allow_delete' => true,
'by_reference' => false,
'entry_options' => ['label' => false],
'prototype' => true, // Genere le template JS
])
{{-- Template Twig avec JS pour ajouter dynamiquement --}}
{{ form_start(form) }}
<div id='commentaires-list'
data-prototype='{{ form_widget(form.commentaires.vars.prototype)|e('html_attr') }}'>
{% for commentaire in form.commentaires %}
<div class='commentaire-item'>
{{ form_row(commentaire.contenu) }}
<button type='button' class='supprimer'>Supprimer</button>
</div>
{% endfor %}
</div>
<button type='button' id='ajouter-commentaire'>+ Ajouter</button>
{{ form_end(form) }}
<script>
document.getElementById('ajouter-commentaire').addEventListener('click', () => {
const list = document.getElementById('commentaires-list');
const prototype = list.dataset.prototype;
const index = list.children.length;
const html = prototype.replace(/__name__/g, index);
list.insertAdjacentHTML('beforeend', '<div class="commentaire-item">' + html + '</div>');
});
</script>
4.3 — Contrainte de validation personnalisee
<?php
// src/Validator/Constraints/MotsDInterdits.php
namespace App\Validator\Constraints;
use Symfony\Component\Validator\Constraint;
#[\Attribute]
class MotsDInterdits extends Constraint {
Cours Symfony Avance — 3eme Annee IT Page 19
public string $message = 'Le texte contient des mots interdits : {{ mots }}.';
public array $mots = ['spam', 'pub', 'promo'];
}
// src/Validator/Constraints/MotsDInterditsValidator.php
class MotsDInterditsValidator extends ConstraintValidator {
public function validate(mixed $value, Constraint $constraint): void {
if (null === $value || '' === $value) return;
foreach ($constraint->mots as $mot) {
if (str_contains(strtolower($value), strtolower($mot))) {
$this->context->buildViolation($constraint->message)
->setParameter('{{ mots }}', $mot)
->addViolation();
}
}
}
}
// Utilisation dans une Entity
#[MotsDInterdits(mots: ['spam', 'promo'])]
private string $description;
Cours Symfony Avance — 3eme Annee IT Page 20
TD 3 — Questions de comprehension
Question 1 — Qu'est-ce qu'un FormType imbrique dans Symfony ? Dans quel cas l'utiliser ?
Reponse :
Question 2 — Quelle est la difference entre mapped: true et mapped: false dans un champ
de formulaire ?
Reponse :
Question 3 — Comment gere-t-on l'upload de fichiers multiples dans Symfony ? Quelles
sont les contraintes disponibles ?
Reponse :
Question 4 — Comment creer une contrainte de validation personnalisee dans Symfony ?
Reponse :
Question 5 — Qu'est-ce que le CollectionType ? Comment permet-il d'ajouter des
sous-formulaires dynamiquement ?
Reponse :
Question 6 — Qu'est-ce que la protection CSRF dans Symfony ? Est-elle activee par defaut
sur les formulaires ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 21
SEANCE 4
Securite Avancee
Voters | JWT | Roles hierarchiques | 2FA
Voters personnalises | Guard Authenticator | JWT avec LexikBundle | Roles et hierarchie | Rate
limiting
5. Voters — Autorisation fine | Seance 4
Les Voters permettent de definir des regles d'autorisation complexes sur des objets
specifiques. Ils repondent a la question : 'Cet utilisateur peut-il faire cette action sur cet
objet ?'
<?php
// src/Security/TicketVoter.php
namespace App\Security;
use App\Entity\Ticket;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
class TicketVoter extends Voter {
const VIEW = 'ticket_view';
const EDIT = 'ticket_edit';
const CLOSE = 'ticket_close';
const DELETE = 'ticket_delete';
protected function supports(string $attribute, mixed $subject): bool {
return in_array($attribute, [self::VIEW, self::EDIT, self::CLOSE, self::DELETE])
&& $subject instanceof Ticket;
}
protected function voteOnAttribute(string $attribute, mixed $ticket,
TokenInterface $token): bool {
$user = $token->getUser();
if (!$user instanceof User) return false;
return match($attribute) {
self::VIEW => $this->peutVoir($ticket, $user),
self::EDIT => $this->peutModifier($ticket, $user),
self::CLOSE => $this->peutFermer($ticket, $user),
Cours Symfony Avance — 3eme Annee IT Page 22
self::DELETE => $user->hasRole('ROLE_ADMIN'),
default => false,
};
}
private function peutVoir(Ticket $t, User $u): bool {
// Admin tout voir, createur ou assigne voient leur ticket
return $u->hasRole('ROLE_ADMIN')
|| $t->getCreateur() === $u
|| $t->getAssigne() === $u;
}
private function peutModifier(Ticket $t, User $u): bool {
return $u->hasRole('ROLE_AGENT')
|| $t->getCreateur() === $u;
}
private function peutFermer(Ticket $t, User $u): bool {
return $u->hasRole('ROLE_AGENT') || $u->hasRole('ROLE_ADMIN');
}
}
<?php
// Utilisation dans un Controller
class TicketController extends AbstractController {
public function show(Ticket $ticket): Response {
// Lance une AccessDeniedException si non autorise
$this->denyAccessUnlessGranted('ticket_view', $ticket);
return $this->render('ticket/show.html.twig', compact('ticket'));
}
public function close(Ticket $ticket): Response {
$this->denyAccessUnlessGranted('ticket_close', $ticket);
// ...
}
}
// Utilisation dans Twig
// {% if is_granted('ticket_edit', ticket) %}
// <a href='...'>Modifier</a>
// {% endif %}
Cours Symfony Avance — 3eme Annee IT Page 23
6. JWT — Authentification API
6.1 — Installation et configuration
# Installer LexikJWTAuthenticationBundle
composer require lexik/jwt-authentication-bundle
# Generer les cles SSL
php bin/console lexik:jwt:generate-keypair
# Cree : config/jwt/private.pem et public.pem
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
secret_key: '%kernel.project_dir%/config/jwt/private.pem'
public_key: '%kernel.project_dir%/config/jwt/public.pem'
pass_phrase: '%env(JWT_PASSPHRASE)%'
token_ttl: 3600 # 1 heure
# config/packages/security.yaml
security:
providers:
app_user_provider:
entity:
class: App\Entity\User
property: email
firewalls:
api_login:
pattern: ^/api/login
stateless: true
json_login:
check_path: /api/login
success_handler: lexik_jwt_authentication.handler.authentication_success
failure_handler: lexik_jwt_authentication.handler.authentication_failure
api:
pattern: ^/api
stateless: true
jwt: ~
access_control:
- { path: ^/api/login, roles: PUBLIC_ACCESS }
- { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
6.2 — Utilisation du JWT
# 1. Obtenir un token
curl -X POST http://localhost:8000/api/login \
-H 'Content-Type: application/json' \
Cours Symfony Avance — 3eme Annee IT Page 24
-d '{"username": "user@example.com", "password": "secret"}'
# Reponse :
# {"token": "eyJ0eXAiOiJKV1Qi..."}
# 2. Utiliser le token dans les requetes suivantes
curl -X GET http://localhost:8000/api/tickets \
-H 'Authorization: Bearer eyJ0eXAiOiJKV1Qi...'
6.3 — Hierarchie des roles
# config/packages/security.yaml
security:
role_hierarchy:
ROLE_AGENT: [ROLE_USER]
ROLE_MANAGER: [ROLE_AGENT]
ROLE_ADMIN: [ROLE_MANAGER]
# ROLE_ADMIN herite de MANAGER -> AGENT -> USER
Cours Symfony Avance — 3eme Annee IT Page 25
TD 4 — Questions de comprehension
Question 1 — Qu'est-ce qu'un Voter Symfony ? En quoi est-il plus precis que les simples
roles ?
Reponse :
Question 2 — Expliquez le fonctionnement d'un JWT. Quelles sont ses 3 parties ?
Reponse :
Question 3 — Pourquoi utilise-t-on des tokens JWT pour les API plutot que des sessions ?
Reponse :
Question 4 — Qu'est-ce que la hierarchie des roles dans Symfony ? Donnez un exemple
concret.
Reponse :
Question 5 — Quelle est la difference entre denyAccessUnlessGranted() et isGranted()
dans un controller ?
Reponse :
Question 6 — Qu'est-ce que le rate limiting ? Comment le mettre en place dans Symfony ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 26
SEANCE 5
API Platform
REST | JSON-LD | Filtres | Serialisation | Operations custom
Installer API Platform | ApiResource | Groupes de serialisation | Filtres | Operations
personnalisees
7. API Platform | Seance 5
API Platform est le framework PHP le plus avance pour creer des APIs REST et GraphQL. Il
genere automatiquement une documentation Swagger/OpenAPI, des endpoints CRUD
complets et supporte JSON-LD, Hydra et JSON:API.
7.1 — Installation et configuration
composer require api
# Ouvre http://localhost:8000/api
# La documentation Swagger est generee automatiquement !
7.2 — Decorator ApiResource sur une entite
<?php
// src/Entity/Ticket.php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Symfony\Component\Serializer\Annotation\Groups;
#[ApiResource(
operations: [
new GetCollection(normalizationContext: ['groups' => ['ticket:list']]),
new Get(normalizationContext: ['groups' => ['ticket:read']]),
new Post(denormalizationContext: ['groups' => ['ticket:write']]),
Cours Symfony Avance — 3eme Annee IT Page 27
new Put(denormalizationContext: ['groups' => ['ticket:write']]),
new Delete(security: 'is_granted("ROLE_ADMIN")'),
],
paginationItemsPerPage: 20,
)]
#[ApiFilter(SearchFilter::class, properties: ['statut' => 'exact', 'priorite' => 'exact',
'titre' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'priorite'])]
class Ticket {
#[Groups(['ticket:list', 'ticket:read'])]
private ?int $id = null;
#[Groups(['ticket:list', 'ticket:read', 'ticket:write'])]
private string $titre;
#[Groups(['ticket:read', 'ticket:write'])]
private string $description;
#[Groups(['ticket:list', 'ticket:read'])]
private string $statut = 'ouvert';
// Le createur est expose en lecture mais pas en ecriture
// (il est defini automatiquement via un StateProcessor)
#[Groups(['ticket:read'])]
private User $createur;
}
Cours Symfony Avance — 3eme Annee IT Page 28
7.3 — StateProcessor personnalise
<?php
// src/State/TicketProcessor.php
namespace App\State;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Ticket;
use Symfony\Bundle\SecurityBundle\Security;
class TicketProcessor implements ProcessorInterface {
public function __construct(
private ProcessorInterface $inner,
private Security $security
) {}
public function process(mixed $data, Operation $operation,
array $uriVariables = [], array $context = []): mixed {
if ($data instanceof Ticket && !$data->getId()) {
// Assigner automatiquement le createur courant
$data->setCreateur($this->security->getUser());
$data->setStatut('ouvert');
}
return $this->inner->process($data, $operation, $uriVariables, $context);
}
}
# services.yaml — lier le processor a l'entite
services:
App\State\TicketProcessor:
bind:
ProcessorInterface $inner: '@api_platform.doctrine.orm.state.persist_processor'
tags:
- name: 'api_platform.state_processor'
7.4 — Tester l'API Platform
# GET /api/tickets — liste avec pagination
curl http://localhost:8000/api/tickets
# GET /api/tickets?statut=ouvert&priorite=haute
curl 'http://localhost:8000/api/tickets?statut=ouvert&priorite=haute'
# GET /api/tickets?order[createdAt]=desc
curl 'http://localhost:8000/api/tickets?order%5BcreatedAt%5D=desc'
# POST /api/tickets — creer
curl -X POST http://localhost:8000/api/tickets \
-H 'Content-Type: application/json' \
Cours Symfony Avance — 3eme Annee IT Page 29
-H 'Authorization: Bearer TOKEN' \
-d '{"titre": "Bug login", "description": "...", "priorite": "haute"}'
Cours Symfony Avance — 3eme Annee IT Page 30
TD 5 — Questions de comprehension
Question 1 — Qu'est-ce qu'API Platform ? Quels sont ses avantages par rapport a une API
REST manuelle ?
Reponse :
Question 2 — A quoi servent les groupes de serialisation dans API Platform ?
Reponse :
Question 3 — Qu'est-ce que le JSON-LD ? En quoi est-ce different du JSON classique ?
Reponse :
Question 4 — Comment restreindre l'acces a une operation API Platform a certains roles ?
Reponse :
Question 5 — Qu'est-ce qu'un StateProcessor API Platform ? Dans quel cas l'utilise-t-on ?
Reponse :
Question 6 — Comment paginer les resultats d'une collection dans API Platform ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 31
SEANCE 6
Symfony Messenger — Files de messages
Bus de commandes | Handlers | Workers | Retry | Async
Installer Messenger | Messages et Handlers | Transport async | Workers | Retry automatique
8. Symfony Messenger | Seance 6
Symfony Messenger est un composant qui permet d'envoyer des messages de maniere
synchrone ou asynchrone via des files de messages (queues). C'est ideal pour les taches
longues : envoi d'emails, generation de PDF, traitement d'images, notifications...
8.1 — Concepts Messenger
SYNCHRONE (immediate) : Controller -> MessageBus -> Handler -> Resultat immediate
ASYNCHRONE (en arriere-plan) : Controller -> MessageBus -> Transport
(Redis/RabbitMQ/DB) -> Worker -> Handler | ^ | Reponse immediate | v (tourne en
arriere-plan) Utilisateur
8.2 — Installation
composer require symfony/messenger
# Pour utiliser Redis comme transport
composer require symfony/redis-messenger
# Pour utiliser la BDD comme transport (plus simple pour debuter)
composer require symfony/doctrine-messenger
php bin/console messenger:setup-transports
8.3 — Creer un Message et son Handler
<?php
// src/Message/EnvoyerEmailTicketMessage.php
namespace App\Message;
class EnvoyerEmailTicketMessage {
public function __construct(
public readonly int $ticketId,
public readonly string $type, // 'creation', 'assignation', 'resolution'
Cours Symfony Avance — 3eme Annee IT Page 32
public readonly string $emailDestinataire
) {}
}
<?php
// src/MessageHandler/EnvoyerEmailTicketHandler.php
namespace App\MessageHandler;
use App\Message\EnvoyerEmailTicketMessage;
use App\Repository\TicketRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
#[AsMessageHandler]
class EnvoyerEmailTicketHandler {
public function __construct(
private MailerInterface $mailer,
private TicketRepository $ticketRepo
) {}
public function __invoke(EnvoyerEmailTicketMessage $message): void {
$ticket = $this->ticketRepo->find($message->ticketId);
if (!$ticket) return;
$sujet = match($message->type) {
'creation' => 'Ticket cree : ' . $ticket->getTitre(),
'assignation'=> 'Ticket assigne : ' . $ticket->getTitre(),
'resolution' => 'Ticket resolu : ' . $ticket->getTitre(),
default => 'Mise a jour ticket',
};
$email = (new Email())
->from('support@monapp.com')
->to($message->emailDestinataire)
->subject($sujet)
->html("<p>Ticket #{$ticket->getId()} : {$ticket->getTitre()}</p>");
$this->mailer->send($email);
}
}
Cours Symfony Avance — 3eme Annee IT Page 33
8.4 — Configuration des transports
# config/packages/messenger.yaml
framework:
messenger:
# Transport asynchrone via la base de donnees
transports:
async:
dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
retry_strategy:
max_retries: 3
delay: 1000 # 1 seconde entre chaque retry
multiplier: 2 # 1s, 2s, 4s
# Transport prioritaire pour les emails critiques
urgent:
dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
options:
queue_name: urgent
# Routage des messages vers les transports
routing:
App\Message\EnvoyerEmailTicketMessage: async
App\Message\NotificationUrgente: urgent
# .env
# Utiliser la BDD comme transport (pour dev)
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
# Utiliser Redis en production
# MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages
8.5 — Dispatcher un message
<?php
// Dans un Controller ou Service
use Symfony\Component\Messenger\MessageBusInterface;
class TicketController extends AbstractController {
public function __construct(
private MessageBusInterface $bus
) {}
public function store(Request $req): Response {
// Creer le ticket...
$ticket = $this->ticketService->creer($req->request->all());
// Envoyer le message de maniere ASYNCHRONE
// Le handler s'executera dans le worker en arriere-plan
$this->bus->dispatch(new EnvoyerEmailTicketMessage(
$ticket->getId(),
Cours Symfony Avance — 3eme Annee IT Page 34
'creation',
$ticket->getCreateur()->getEmail()
));
// La reponse est immediate, l'email part en arriere-plan
return $this->json(['message' => 'Ticket cree'], 201);
}
}
8.6 — Lancer le Worker
# Lancer le worker (consomme les messages de la file)
php bin/console messenger:consume async
# Lancer plusieurs workers en parallel
php bin/console messenger:consume async urgent --time-limit=3600
# Voir les messages en attente
php bin/console messenger:stats
# Messages qui ont echoue (apres tous les retries)
php bin/console messenger:failed:show
# Retenter les messages echoues
php bin/console messenger:failed:retry
Cours Symfony Avance — 3eme Annee IT Page 35
TD 6 — Questions de comprehension
Question 1 — Qu'est-ce que Symfony Messenger ? Quelle est la difference entre un
message synchrone et asynchrone ?
Reponse :
Question 2 — Qu'est-ce qu'un transport Messenger ? Citez 3 exemples de transports
disponibles.
Reponse :
Question 3 — Qu'est-ce qu'un Worker Messenger ? Que se passe-t-il si le worker est arrete
?
Reponse :
Question 4 — Comment fonctionne le systeme de retry dans Messenger ? Que se passe-t-il
apres les max_retries ?
Reponse :
Question 5 — Dans quel cas prefere-t-on traiter un message de maniere asynchrone plutot
que synchrone ?
Reponse :
Question 6 — Comment router differents types de messages vers differents transports ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 36
SEANCE 7
Tests Automatises avec Symfony
PHPUnit | WebTestCase | Fixtures | TDD
PHPUnit et assertions | Tests unitaires | Tests fonctionnels | DataFixtures | TDD
9. Tests avec Symfony et PHPUnit | Seance 7
9.1 — Installation
composer require --dev phpunit/phpunit symfony/test-pack
composer require --dev doctrine/doctrine-fixtures-bundle
composer require --dev zenstruck/foundry
# Creer la BDD de test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
# Lancer tous les tests
php bin/phpunit
# Lancer avec coverage HTML
php bin/phpunit --coverage-html coverage/
9.2 — Tests unitaires
<?php
// tests/Unit/Service/TicketServiceTest.php
namespace App\Tests\Unit\Service;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\TicketRepository;
use App\Service\TicketService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
class TicketServiceTest extends TestCase {
private TicketService $service;
private TicketRepository $repo;
Cours Symfony Avance — 3eme Annee IT Page 37
protected function setUp(): void {
// Creer des mocks des dependances
$this->repo = $this->createMock(TicketRepository::class);
$dispatcher = $this->createMock(EventDispatcherInterface::class);
$this->service = new TicketService($this->repo, $dispatcher);
}
public function testCreerTicketStatutInitialEstOuvert(): void {
// Arrange
$user = new User();
$this->repo->expects($this->once())->method('save');
// Act
$ticket = $this->service->creer(['titre' => 'Bug login'], $user);
// Assert
$this->assertEquals('ouvert', $ticket->getStatut());
$this->assertEquals($user, $ticket->getCreateur());
}
public function testFermerTicketChangeStatut(): void {
$ticket = new Ticket();
$ticket->setStatut('en_cours');
$this->repo->expects($this->once())->method('save');
$this->service->fermer($ticket);
$this->assertEquals('ferme', $ticket->getStatut());
$this->assertNotNull($ticket->getFermeAt());
}
}
Cours Symfony Avance — 3eme Annee IT Page 38
9.3 — Tests fonctionnels (WebTestCase)
<?php
// tests/Functional/Controller/TicketControllerTest.php
namespace App\Tests\Functional\Controller;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
class TicketControllerTest extends WebTestCase {
public function testListeTicketsNonConnecteRedirige(): void {
$client = static::createClient();
$client->request('GET', '/tickets');
$this->assertResponseRedirects('/login');
}
public function testListeTicketsConnecteAfficheListe(): void {
$client = static::createClient();
// Authentifier un utilisateur
$em = static::getContainer()->get(EntityManagerInterface::class);
$user = $em->getRepository(User::class)->findOneBy(['email' => 'user@test.com']);
$client->loginUser($user);
$crawler = $client->request('GET', '/tickets');
$this->assertResponseIsSuccessful();
$this->assertSelectorExists('table.tickets-table');
$this->assertSelectorExists('.ticket-row');
}
public function testCreerTicketSoumettreFormulaire(): void {
$client = static::createClient();
$user = $this->getUser($client);
$client->loginUser($user);
$client->request('GET', '/tickets/new');
$this->assertResponseIsSuccessful();
$client->submitForm('Creer le ticket', [
'ticket[titre]' => 'Bug de connexion urgent',
'ticket[description]' => 'Impossible de se connecter depuis ce matin.',
'ticket[priorite]' => 'haute',
]);
$this->assertResponseRedirects('/tickets');
$client->followRedirect();
$this->assertSelectorTextContains('.flash-success', 'Ticket cree');
Cours Symfony Avance — 3eme Annee IT Page 39
}
public function testAPICreerTicketRetourne201(): void {
$client = static::createClient();
$client->request('POST', '/api/login', content: json_encode([
'username' => 'user@test.com',
'password' => 'password',
]));
$token = json_decode($client->getResponse()->getContent())->token;
$client->request('POST', '/api/tickets',
server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
content: json_encode([
'titre' => 'Bug API Test',
'description' => 'Description du bug de test',
'priorite' => 'normale',
])
);
$this->assertResponseStatusCodeSame(201);
$data = json_decode($client->getResponse()->getContent(), true);
$this->assertEquals('ouvert', $data['statut']);
}
}
Cours Symfony Avance — 3eme Annee IT Page 40
TD 7 — Questions de comprehension
Question 1 — Quelle est la difference entre un test unitaire et un test fonctionnel dans
Symfony ?
Reponse :
Question 2 — Qu'est-ce qu'un mock (simulacre) ? Pourquoi l'utilise-t-on dans les tests
unitaires ?
Reponse :
Question 3 — Qu'est-ce que les DataFixtures Symfony ? A quoi servent-elles dans les tests
?
Reponse :
Question 4 — Comment tester une route protegee (authentification requise) avec
WebTestCase ?
Reponse :
Question 5 — Qu'est-ce que le TDD (Test Driven Development) ? Decrivez le cycle
Red-Green-Refactor.
Reponse :
Question 6 — Qu'est-ce que le code coverage ? Un coverage de 100% garantit-il un code
sans bug ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 41
SEANCE 8
Performance et Cache
HTTP Cache | Redis | Profiler | Optimisation Doctrine
HTTP Cache | Redis avec Cache | Symfony Profiler | Optimisation requetes | Lazy loading
10. Cache dans Symfony | Seance 8
10.1 — Cache HTTP
<?php
// Mise en cache HTTP d'une reponse
class TicketController extends AbstractController {
#[Route('/api/tickets', methods: ['GET'])]
public function index(TicketRepository $repo): Response {
$tickets = $repo->findAll();
$response = $this->json($tickets);
// Cache public pendant 60 secondes
$response->setPublic();
$response->setMaxAge(60);
$response->setSharedMaxAge(60);
// ETag pour la validation
$response->setEtag(md5(serialize($tickets)));
$response->isNotModified($this->getRequest()) && $response->setStatusCode(304);
return $response;
}
}
10.2 — Cache applicatif avec Redis
# config/packages/cache.yaml
framework:
cache:
app: cache.adapter.redis
default_redis_provider: 'redis://localhost'
pools:
tickets.cache:
Cours Symfony Avance — 3eme Annee IT Page 42
adapter: cache.adapter.redis
default_lifetime: 300 # 5 minutes
<?php
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
class TicketService {
public function __construct(
private CacheInterface $cache,
private TicketRepository $repo
) {}
public function getStatistiques(): array {
// Mettre en cache pendant 5 minutes
return $this->cache->get('tickets.stats', function(ItemInterface $item) {
$item->expiresAfter(300);
return $this->repo->getStatistiquesParStatut();
});
}
public function invaliderCacheStats(): void {
$this->cache->delete('tickets.stats');
}
}
Profiler
Symfony
Le Profiler est accessible a http://localhost:8000/_profiler. Il montre toutes les
requetes SQL executees, leur duree, les services charges, la memoire utilisee
et les evenements dispatches. C'est l'outil indispensable pour detecter les
problemes de performance.
Cours Symfony Avance — 3eme Annee IT Page 43
TD 8 — Questions de comprehension
Question 1 — Quelle est la difference entre le cache HTTP et le cache applicatif ?
Reponse :
Question 2 — Qu'est-ce qu'un ETag HTTP ? Comment l'utilise-t-on pour valider le cache ?
Reponse :
Question 3 — Comment configurer Redis comme adaptateur de cache dans Symfony ?
Reponse :
Question 4 — Qu'est-ce que le Symfony Profiler ? Citez 5 informations qu'il fournit.
Reponse :
Question 5 — Comment detecter et corriger le probleme N+1 avec le Profiler Symfony ?
Reponse :
Question 6 — Qu'est-ce que la serialisation circulaire dans Doctrine ? Comment l'eviter ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 44
SEANCE 9
Microservices avec Symfony
Architecture | Communication HTTP | Messenger | API Gateway
Monolithe vs Microservices | HttpClient Symfony | Messenger entre services | API Gateway |
Patterns
11. Architecture Microservices | Seance 9
11.1 — Monolithe vs Microservices
Critere Monolithe Microservices
Deploiement Un seul deployable Un deploiement par service
Scalabilite Tout le monolithe scale Scale service par service
Technologie Une seule stack Stack differente par service
Communication Appels de fonctions HTTP, messages, events
Complexite Faible au debut Elevee (reseau, latence, coherence)
Equipe 1 equipe 1 equipe par service
Quand choisir Debut de projet, petite equipe Systeme mature, grande equipe
ARCHITECTURE MICROSERVICES — Systeme de tickets +------------------+ | API
Gateway | <- Point d'entree unique (Nginx / Traefik) +------------------+ | | | |
v v v v +-------+ +-------+ +-------+ +----------+ |Ticket | | User | | Notif | |
Search | |Service| |Service| |Service| | Service | +-------+ +-------+ +-------+
+----------+ | | ^ | | | | | +-----> Message Bus (Messenger / RabbitMQ) <----+
(communication asynchrone entre services)
11.2 — Communication HTTP entre services
<?php
// src/Service/UserServiceClient.php
// Le service Ticket appelle le service User via HTTP
namespace App\Service;
use Symfony\Contracts\HttpClient\HttpClientInterface;
class UserServiceClient {
Cours Symfony Avance — 3eme Annee IT Page 45
public function __construct(
private HttpClientInterface $client
) {}
public function getUser(int $userId): array {
$response = $this->client->request('GET',
"http://user-service/api/users/{$userId}",
['headers' => ['Authorization' => 'Bearer ' . $this->getServiceToken()]]
);
return $response->toArray();
}
public function getUsersByIds(array $ids): array {
$response = $this->client->request('POST',
'http://user-service/api/users/batch',
['json' => ['ids' => $ids]]
);
return $response->toArray();
}
private function getServiceToken(): string {
// Token de service (machine to machine)
return $_ENV['USER_SERVICE_TOKEN'];
}
}
Cours Symfony Avance — 3eme Annee IT Page 46
11.3 — Circuit Breaker Pattern
Le Circuit Breaker (disjoncteur) protege un microservice des pannes en cascade. Si un
service distant est indisponible, le circuit s'ouvre et retourne une reponse par defaut plutot
que d'attendre un timeout.
<?php
// src/Service/ResilientUserServiceClient.php
class ResilientUserServiceClient {
private int $echecConsecutifs = 0;
private ?\DateTimeImmutable $ouvertDepuis = null;
private int $seuilOuverture = 5; // Ouvre apres 5 echecs
private int $delaiRetry = 30; // Retente apres 30 secondes
public function getUser(int $userId): array {
// Circuit ouvert : retourner valeur par defaut
if ($this->estOuvert()) {
return ['id' => $userId, 'nom' => 'Utilisateur inconnu'];
}
try {
$user = $this->client->request('GET',
"http://user-service/api/users/{$userId}")-> toArray();
$this->enregistrerSucces();
return $user;
} catch (\Exception $e) {
$this->enregistrerEchec();
return ['id' => $userId, 'nom' => 'Utilisateur inconnu'];
}
}
private function estOuvert(): bool {
if ($this->echecConsecutifs < $this->seuilOuverture) return false;
if (!$this->ouvertDepuis) return false;
$secondesEcoulees = time() - $this->ouvertDepuis->getTimestamp();
return $secondesEcoulees < $this->delaiRetry;
}
private function enregistrerEchec(): void {
$this->echecConsecutifs++;
if ($this->echecConsecutifs >= $this->seuilOuverture) {
$this->ouvertDepuis = new \DateTimeImmutable();
}
}
private function enregistrerSucces(): void {
$this->echecConsecutifs = 0;
$this->ouvertDepuis = null;
}
}
Cours Symfony Avance — 3eme Annee IT Page 47
Cours Symfony Avance — 3eme Annee IT Page 48
TD 9 — Questions de comprehension
Question 1 — Quels sont les avantages et inconvenients de l'architecture microservices par
rapport au monolithe ?
Reponse :
Question 2 — Comment deux microservices Symfony communiquent-ils de maniere
synchrone ?
Reponse :
Question 3 — Comment deux microservices Symfony communiquent-ils de maniere
asynchrone ?
Reponse :
Question 4 — Qu'est-ce que le pattern Circuit Breaker ? Quel probleme resout-il ?
Reponse :
Question 5 — Qu'est-ce qu'un API Gateway ? Quelles fonctions peut-il assurer ?
Reponse :
Question 6 — Qu'est-ce que le pattern Saga pour la gestion des transactions distribuees ?
Reponse :
Cours Symfony Avance — 3eme Annee IT Page 49
SEANCE 10
Projet Final — Systeme de Tickets
Support
Application web + API REST + Messenger + Tests
Architecture complete | Web + API | Notifications async | Roles et permissions | Tests
12. Projet Final — Systeme de Tickets Support |
Seance 10
12.1 — Presentation du projet
Tu vas construire un systeme de support client complet. Les clients soumettent des tickets,
les agents les traitent et les ferment. Des notifications sont envoyees par email a chaque
changement de statut. L'application expose aussi une API REST pour une future application
mobile.
12.2 — Fonctionnalites requises
• Inscription et connexion des clients (ROLE_USER) et agents (ROLE_AGENT) et admins
(ROLE_ADMIN)
• Soumission de tickets avec titre, description, priorite, categorie(s) et pieces jointes
• Liste des tickets avec filtres (statut, priorite, categorie, mot-cle) et pagination
• Assignation des tickets aux agents par les managers
• Ajout de commentaires sur les tickets (clients et agents)
• Changement de statut : ouvert -> en_cours -> resolu -> ferme
• Notifications email asynchrones via Messenger a chaque changement
• Dashboard admin : stats par statut, priorite, agent
• API REST complete via API Platform (tickets + commentaires)
• Suite de tests unitaires et fonctionnels
12.3 — Architecture du projet
src/
|-- Controller/
| |-- SecurityController.php (login/logout/register)
| |-- TicketController.php (CRUD web)
| |-- CommentaireController.php
Cours Symfony Avance — 3eme Annee IT Page 50
| |-- AdminController.php (dashboard admin)
|-- Entity/
| |-- User.php
| |-- Ticket.php
| |-- Commentaire.php
| |-- Categorie.php
| |-- PieceJointe.php
|-- Form/
| |-- TicketType.php
| |-- CommentaireType.php
| |-- RegistrationType.php
|-- Message/
| |-- NotificationTicketMessage.php
|-- MessageHandler/
| |-- NotificationTicketHandler.php
|-- Repository/
| |-- TicketRepository.php
| |-- UserRepository.php
|-- Security/
| |-- TicketVoter.php
|-- Service/
| |-- TicketService.php
| |-- StatistiquesService.php
|-- State/ (API Platform)
| |-- TicketProcessor.php
|-- Event/
| |-- TicketCreatedEvent.php
| |-- TicketStatusChangedEvent.php
|-- EventSubscriber/
| |-- TicketNotificationSubscriber.php
|-- DataFixtures/
|-- AppFixtures.php
Cours Symfony Avance — 3eme Annee IT Page 51
12.4 — Commandes de mise en place
# 1. Creer le projet
symfony new support-tickets --version=stable --webapp
cd support-tickets
# 2. Installer les dependances
composer require api
composer require lexik/jwt-authentication-bundle
composer require symfony/messenger symfony/mailer
composer require --dev doctrine/doctrine-fixtures-bundle phpunit/phpunit
# 3. Configurer .env
DATABASE_URL='mysql://root:@127.0.0.1:3306/support_tickets'
MAILER_DSN=smtp://localhost
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=mysecretphrase
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
# 4. Creer la BDD et migrer
php bin/console doctrine:database:create
php bin/console make:migration
php bin/console doctrine:migrations:migrate
# 5. Generer les cles JWT
php bin/console lexik:jwt:generate-keypair
# 6. Charger les fixtures
php bin/console doctrine:fixtures:load
# 7. Lancer le serveur et le worker
symfony serve &
php bin/console messenger:consume async &
12.5 — Points de verification du projet
Critere Verification Points
Entites et relations Migration sans erreur, 5 tables creees 10
Authentification Login/logout/register fonctionnels, 3 roles 15
CRUD Tickets Creer/voir/modifier/fermer avec voter 20
Commentaires Ajouter des commentaires avec upload 10
Notifications Email envoye via Messenger (worker) 15
API Platform Endpoints CRUD avec JWT et filtres 15
Tests Min 10 tests unitaires + 5 fonctionnels 10
Cours Symfony Avance — 3eme Annee IT Page 52
Dashboard admin Stats par statut, priorite, agent 5
Cours Symfony Avance — 3eme Annee IT Page 53
Exercice PROJET FINAL — Livrables attendus
1
.
Code source complet sur GitHub avec un README detaillant l'installation et
l'architecture.
2
.
Application web fonctionnelle avec les 3 roles (client, agent, admin) et tous les
workflows.
3
.
API REST documentee via Swagger (/api) avec authentification JWT operationnelle.
4
.
Worker Messenger configure et notifications email fonctionnelles.
5
.
Suite de tests : min 10 tests unitaires du TicketService + 5 tests fonctionnels des
routes.
6
.
Bonus : Dashboard avec graphiques (Chart.js) montrant l'evolution des tickets par
semaine.
7
.
Bonus avance : Ajouter une recherche full-text avec Elasticsearch via
FOSElasticaBundle.
Cours Symfony Avance — 3eme Annee IT Page 54
Recapitulatif du cours — 10 seances
Seanc
e Theme Competences cles
1 Architecture DI + Events Container, Auto-wiring, EventDispatcher, Subscribers
2 Doctrine ORM avance Relations, QueryBuilder, DQL, N+1, Indexes
3 Formulaires avances Collections, Upload, Contraintes custom, CSRF
4 Securite avancee Voters, JWT, Roles hierarchiques, Rate limiting
5 API Platform ApiResource, Groupes serialisation, Filtres,
StateProcessor
6 Messenger Messages, Handlers, Transports, Workers, Retry
7 Tests automatises PHPUnit, Mocks, WebTestCase, Fixtures, TDD
8 Performance et Cache HTTP Cache, Redis, Profiler, Optimisation Doctrine
9 Microservices Architecture, HttpClient, Circuit Breaker, API Gateway
10 Projet — Systeme tickets Web + API + Messenger + Voters + Tests
Competenc
es
acquises
A l'issue de ce cours avance de 40 heures, vous maitrisez Symfony au niveau
professionnel. Vous savez concevoir des architectures d'entreprise avec
injection de dependances, securiser des APIs avec JWT et Voters, traiter des
taches asynchrones avec Messenger, tester votre code avec PHPUnit,
optimiser les performances avec le cache et le Profiler, et decouvrir votre
application en microservices. Ces competences sont directement applicables
en entreprise dans des equipes backend PHP.

=== Ingenius Tracking PayPal ===
Contributors: ingeniusagency
Donate link: https://ingenius.agency
Tags: paypal, woocommerce, tracking, shipping, automation, aftership
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Ingenius Tracking PayPal automatise l'envoi des numéros de suivi vers l'API PayPal Payments pour garder vos litiges fermés et vos clients informés.

== Description ==

Ce plugin WooCommerce récupère les numéros de suivi (AfterShip ou méta personnalisée) et les informations transporteur pour toutes les commandes réglées via PayPal Commerce Platform (PPCP). Chaque enregistrement ou modification de suivi déclenche l'API PayPal afin d'ajouter ou de mettre à jour les trackers nécessaires côté PayPal, ce qui permet de réduire les litiges et d'afficher aux acheteurs l'état de la livraison.

### Fonctionnalités clés

* **Détection automatique** des numéros de suivi et transporteurs depuis AfterShip, Send to AfterShip ou champs personnalisés importés.
* **Support des imports & éditions** : qu'il s'agisse d'une sauvegarde manuelle, d'un import WP All Import ou d'un upsell PPCP, PayPal est synchronisé automatiquement.
* **Suivi de statut** : chaque commande dispose d'une méta `itp_send_to_ppl` et d'une note détaillant le succès ou l'erreur renvoyée par PayPal.
* **Page d'administration dédiée** (WooCommerce → PayPal Tracking) avec statistiques, bouton de synchronisation en masse et traitement AJAX en lots.
* **Gestion multilingue** : transporteur par défaut défini selon la langue du site lorsque l'information manque.

### Pourquoi l'utiliser ?

* Réduire les litiges PayPal en fournissant des preuves d'expédition automatiquement.
* Harmoniser les données entre WooCommerce, AfterShip et PayPal Payments.
* Donner de la visibilité aux équipes support grâce aux notes automatiques ajoutées sur les commandes.

== Installation ==

1. Téléversez le dossier du plugin dans `/wp-content/plugins/` ou installez-le via l'interface WordPress.
2. Activez le plugin depuis le menu *Extensions*.
3. Assurez-vous que WooCommerce PayPal Payments est configuré (client ID/secret).
4. Ajoutez vos numéros de suivi habituels (AfterShip, imports, etc.) : l'envoi vers PayPal est automatique.

== Utilisation ==

* Rendez-vous dans *WooCommerce → PayPal Tracking* pour visualiser les statistiques (en attente, envoyés, total) et déclencher la synchronisation en masse des commandes comportant `itp_send_to_ppl = 0`.
* Consultez les notes système d'une commande pour vérifier le dernier statut d'envoi ou l'erreur remontée par l'API PayPal.

== Frequently Asked Questions ==

= Quels transporteurs sont pris en charge ? =
Les principaux transporteurs AfterShip sont mappés automatiquement. Si un transporteur n'est pas reconnu, le plugin vous envoie une notification afin que vous puissiez l'ajouter ou corriger l'information.

= Puis-je relancer l'envoi d'un suivi ? =
Oui. Modifiez la commande puis sauvegardez-la, ou utilisez la page *WooCommerce → PayPal Tracking* pour lancer une synchronisation en masse. Le plugin ne renvoie que les commandes marquées `itp_send_to_ppl = 0`.

= L'import WP All Import est-il compatible ? =
Oui, le plugin écoute `pmxi_saved_post` et déclenche l'envoi vers PayPal dès qu'un tracking est importé.

== Screenshots ==

1. Page d'administration PayPal Tracking avec statistiques et bouton de synchronisation AJAX.
2. Note automatique ajoutée sur la commande WooCommerce après envoi du suivi à PayPal.

== Changelog ==

= 2.0.1.4 =
* Empêcher l'appel API Paypal si le tracking number n'est pas renseigné

= 2.0.1.3 =
* Ajout de la métadonnée `itp_send_to_ppl` pour tracer l'état d'envoi et possibilité de synchroniser en masse depuis une page WooCommerce dédiée.

= 2.0.1.2 =
* Ajout de notes automatiques sur les commandes avec le détail des succès/erreurs retournés par l'API PayPal.

= 2.0.1.1 =
* Correction de l'update checker GitHub.

= 2.0.1 =
* Première intégration de l'update checker.

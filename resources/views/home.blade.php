<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }} - Accueil</title>
        <style>
            body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; margin: 2rem; color: #111827; }
            .container { max-width: 800px; margin: 0 auto; }
            h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
            h2 { font-size: 1.25rem; margin: 1.5rem 0 0.5rem; }
            p { margin-bottom: 1rem; line-height: 1.6; }
            ul { margin: 0 0 1rem 1.25rem; }
            li { margin-bottom: 0.5rem; }
            .muted { color: #6b7280; font-size: 0.9rem; }
            a { color: #2563eb; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Politique de confidentialité</h1>
            <p class="muted">Dernière mise à jour : 10 février 2026</p>
            <p>Cette politique décrit comment {{ config('app.name', 'Laravel') }} collecte, utilise et protège vos données lorsque vous utilisez l'application mobile et les services associés.</p>

            <h2>Données collectées</h2>
            <ul>
                <li>Informations de compte : nom, email, téléphone (si fourni).</li>
                <li>Données liées aux tontines : noms, montants, fréquences, participants, historiques de cotisations.</li>
                <li>Notifications : identifiants nécessaires pour recevoir des alertes (si activées).</li>
            </ul>

            <h2>Utilisation des données</h2>
            <ul>
                <li>Fournir les fonctionnalités principales (création, gestion et suivi des tontines).</li>
                <li>Assurer la sécurité du compte et l'authentification.</li>
                <li>Envoyer des notifications et rappels liés aux cotisations.</li>
                <li>Améliorer la stabilité et les performances de l'application.</li>
            </ul>

            <h2>Partage des données</h2>
            <p>Vos données ne sont pas vendues. Elles peuvent être partagées uniquement avec des prestataires techniques indispensables au fonctionnement du service (hébergement, envoi de notifications) et uniquement dans la limite du nécessaire.</p>

            <h2>Stockage et sécurité</h2>
            <ul>
                <li>Les données sont stockées sur des serveurs sécurisés.</li>
                <li>Les échanges avec nos serveurs sont chiffrés (HTTPS).</li>
                <li>Les jetons d'accès sont protégés dans l'appareil.</li>
            </ul>

            <h2>Conservation</h2>
            <p>Les données sont conservées tant que votre compte est actif, puis supprimées ou anonymisées selon les obligations légales.</p>

            <h2>Vos droits</h2>
            <ul>
                <li>Accès, rectification et suppression de vos données.</li>
                <li>Opposition ou limitation de certains traitements.</li>
                <li>Portabilité de vos données sur demande.</li>
            </ul>
            <p>Pour exercer vos droits, contactez-nous : <a href="mailto:ismalsacko@yahoo.fr">ismalsacko@yahoo.fr</a>.</p>

            <h2>Mineurs</h2>
            <p>L'application n'est pas destinée aux enfants de moins de 13 ans.</p>

            <h2>Changements</h2>
            <p>Nous pouvons mettre à jour cette politique. La date de mise à jour en haut de page indique la dernière version.</p>

        </div>
    </body>
</html>

# ⚙️ Convertisseur Industriel - Les Temps Modernes


Une application web PHP élégante de conversion de fichiers avec un design steampunk inspiré du film "Les Temps Modernes" de Charlie Chaplin. Engrenages, vapeur et machines industrielles pour transformer vos fichiers ! 🎩⚙️

## ✨ Fonctionnalités

### 🖼️ Conversion d'Images
- **JPG → WebP** - Compression moderne optimisée
- **WebP → JPG** - Retour au format universel
- **PNG → JPG** - Conversion avec fond blanc automatique
- **JPG → PNG** - Préservation de la qualité

### 📊 Conversion de Données
- **CSV → JSON** - Structure hiérarchique avec headers
- **JSON → CSV** - Export tabulaire
- **CSV → XLSX** - Excel compatible avec encodage Windows-1252 (caractères spéciaux français)

## 🎨 Design

- **Thème Steampunk** inspiré des "Temps Modernes"
- Engrenages animés en rotation continue
- Effets de vapeur montante
- Palette bronze/cuivre/sépia
- **Responsive design** - Mobile, tablette et desktop
- Animations fluides et transitions élégantes

## 📋 Prérequis

### Serveur
- **PHP 7.4+** ou supérieur
- Extensions PHP requises :
  - `GD Library` - Pour la manipulation d'images
  - `ZipArchive` - Pour la génération de fichiers XLSX
  - `JSON` - Natif dans PHP moderne
  - `mbstring` - Pour la gestion des encodages

### Vérification des extensions

```bash
php -m | grep -E 'gd|zip|json|mbstring'
```

Si une extension manque, installez-la :

```bash
# Debian/Ubuntu
sudo apt-get install php-gd php-zip php-mbstring

# CentOS/RHEL
sudo yum install php-gd php-zip php-mbstring

# Windows (XAMPP/WAMP)
# Décommentez les lignes dans php.ini :
# extension=gd
# extension=zip
# extension=mbstring
```

## 🚀 Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-username/convertisseur-industriel.git
cd convertisseur-industriel
```

### 2. Configuration du serveur web

#### Apache (.htaccess)

Créez un fichier `.htaccess` :

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Augmenter les limites d'upload
php_value upload_max_filesize 50M
php_value post_max_size 50M
php_value memory_limit 256M
php_value max_execution_time 300
```

#### Nginx

```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /var/www/convertisseur-industriel;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 3. Permissions

```bash
chmod 755 index.php
chown www-data:www-data index.php  # Linux
```

### 4. Lancer le serveur

#### Serveur intégré PHP (développement)

```bash
php -S localhost:8000
```

Accédez à : `http://localhost:8000`

#### Production

Déployez sur votre serveur Apache/Nginx avec PHP-FPM.

## 📖 Utilisation

1. **Sélectionnez le type de conversion** dans le menu déroulant
2. **Choisissez votre fichier** à convertir
3. **Cliquez sur "Lancer la Machine"** ⚙️
4. Le fichier converti se télécharge automatiquement

### Formats acceptés

| Type | Extensions acceptées |
|------|---------------------|
| Images | `.jpg`, `.jpeg`, `.png`, `.webp` |
| Données | `.csv`, `.json` |

### Limites par défaut

- Taille maximale : **50 MB**
- Temps d'exécution : **5 minutes**
- Mémoire allouée : **256 MB**

*Modifiables dans `.htaccess` ou `php.ini`*

## 🔧 Configuration avancée

### Personnalisation des limites

Éditez `index.php` en haut du fichier :

```php
ini_set('memory_limit', '512M');        // Mémoire
ini_set('max_execution_time', '600');   // Timeout
ini_set('upload_max_filesize', '100M'); // Taille upload
ini_set('post_max_size', '100M');       // Taille POST
```

### Qualité des images

Modifiez les paramètres de compression :

```php
// WebP (ligne ~34)
imagewebp($img, null, 80);  // 0-100, défaut: 80

// JPEG (ligne ~41)
imagejpeg($img, null, 90);  // 0-100, défaut: 90
```

## 🐛 Résolution de problèmes

### Erreur "Call to undefined function imagecreatefromjpeg()"
➡️ Installez l'extension GD : `sudo apt-get install php-gd`

### Erreur "Class 'ZipArchive' not found"
➡️ Installez l'extension Zip : `sudo apt-get install php-zip`

### Fichier trop volumineux
➡️ Augmentez `upload_max_filesize` et `post_max_size` dans `php.ini` ou `.htaccess`

### XLSX vide ou corrompu
➡️ Vérifiez que votre CSV utilise bien des virgules comme séparateurs
➡️ Assurez-vous que le CSV est en UTF-8

### Caractères spéciaux mal affichés
➡️ Le convertisseur gère automatiquement UTF-8, ISO-8859-1 et Windows-1252
➡️ Pour XLSX, l'encodage est préservé automatiquement

## 🤝 Contribution

Les contributions sont les bienvenues ! 

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/amelioration`)
3. Committez vos changements (`git commit -m 'Ajout fonctionnalité'`)
4. Pushez vers la branche (`git push origin feature/amelioration`)
5. Ouvrez une Pull Request

### Idées de fonctionnalités futures

- [ ] Conversion par lots (multiple fichiers)
- [ ] Prévisualisation avant conversion
- [ ] Historique des conversions
- [ ] Support de formats additionnels (PDF, DOCX, etc.)
- [ ] API REST pour intégration
- [ ] Mode sombre/clair
- [ ] Compression ZIP de plusieurs fichiers

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 👨‍💻 Auteur

Créé avec ⚙️ et inspiré par les machines industrielles genre steampunk

## 🙏 Remerciements

- Communauté PHP pour les extensions GD et ZipArchive
- Claude AI

## 📸 Captures d'écran

### Interface principale
![Interface](https://github.com/sbois/convertisseur-de-fichiers-csv-xlsx-json-jpg-webp-png/blob/main/capture.png?raw=true)

### Version mobile
![Mobile](https://github.com/sbois/convertisseur-de-fichiers-csv-xlsx-json-jpg-webp-png/blob/main/capture_mobile.png?raw=true)

---

⚙️ **Fait avec passion et engrenages** ⚙️

*Si vous aimez ce projet, n'hésitez pas à lui donner une ⭐ sur GitHub !*

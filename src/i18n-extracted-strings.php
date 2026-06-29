<?php

/**
 * i18n extraction catalogue — NOT executed at runtime.
 *
 * Holds back-office labels that are passed to __() via constants or default
 * parameter values, which wp-cli make-pot cannot extract automatically.
 * Keep in sync with the constant/default values they mirror.
 *
 * Sources:
 * - src/Fields/Tabs/*Tab.php default $label values
 * - src/Fields/Text/HeadingField.php LABEL and TAGS_LABEL constants
 * - src/Fields/Text/UptitleField.php LABEL constant
 * - src/Fields/Text/IconField.php LABEL constant
 * - src/Fields/Text/WysiwygField.php default $label value
 * - src/Fields/TrueFalseField.php LABEL constant
 * - src/Admin/ShareOptionsAdmin.php SHARE_FIELD_MAP label strings
 * - src/Enum/BlockCategoriesEnum.php ASSOCIATIONS title strings
 */

// Tab default labels
__('Contenu', 'horizon-tools');
__('Global', 'horizon-tools');
__('Mise en page', 'horizon-tools');
__('Média', 'horizon-tools');
__('Paramètres', 'horizon-tools');

// Field LABEL constants and default values
__('Titre', 'horizon-tools');
__('Tag HTML', 'horizon-tools');
__('Sur-titre', 'horizon-tools');
__('Icône', 'horizon-tools');
__('Description', 'horizon-tools');
__('Vrai/Faux', 'horizon-tools');

// ShareOptionsAdmin::SHARE_FIELD_MAP label strings
__('Activer la copie du lien', 'horizon-tools');
__('Activer le partage par e-mail', 'horizon-tools');
__('Activer le partage par SMS', 'horizon-tools');
__('Activer le partage par WhatsApp', 'horizon-tools');
__('Activer le partage par Messenger', 'horizon-tools');
__('Activer le partage par ChatGPT', 'horizon-tools');
__('Activer le partage par Claude', 'horizon-tools');
__('Activer le partage par Perplexity', 'horizon-tools');
__('Activer le partage par Facebook', 'horizon-tools');
__('Activer le partage par Instagram', 'horizon-tools');
__('Activer le partage par LinkedIn', 'horizon-tools');
__('Activer le partage par X', 'horizon-tools');

// BlockCategoriesEnum::ASSOCIATIONS title strings
__('Haut de page', 'horizon-tools');
__('Blog', 'horizon-tools');
__('Actions', 'horizon-tools');
__('Réassurance', 'horizon-tools');
__('Listes', 'horizon-tools');
__('Navigation', 'horizon-tools');
__('Texte et Images', 'horizon-tools');
__('Remontées automatiques', 'horizon-tools');
__('Accordéons', 'horizon-tools');
__('Relation', 'horizon-tools');
__('Téléchargement', 'horizon-tools');
__('Témoignages', 'horizon-tools');
__('Autres', 'horizon-tools');

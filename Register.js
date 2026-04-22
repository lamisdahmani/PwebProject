//dom= document object model (yjib html f js)
function afficherErreur(inputId, errId, message) {
  const input = document.getElementById(inputId);
  const span  = document.getElementById(errId);
  if (input) input.classList.add('invalid');
  if (span)  span.textContent = message;
}

function effacerErreur(inputId, errId) {
  const input = document.getElementById(inputId);
  const span  = document.getElementById(errId);
  if (input) input.classList.remove('invalid');
  if (span)  span.textContent = '';
}


//RÈGLES DE VALIDATION


/* Champ texte obligatoire */
function validerTexte(inputId, errId, libelle) {
  const val = document.getElementById(inputId).value.trim();
  if (!val) {
    afficherErreur(inputId, errId, libelle + ' est obligatoire.');
    return false;
  }
  effacerErreur(inputId, errId);
  return true;
}

/* NIN : exactement 18 chiffres */
function validerNIN() {
  const val = document.getElementById('nin').value.trim();
  if (!/^[0-9]{18}$/.test(val)) {
    afficherErreur('nin', 'err-nin', 'Le NIN doit comporter exactement 18 chiffres.');
    return false;
  }
  effacerErreur('nin', 'err-nin');
  return true;
}

/* Date de naissance : requise + âge minimum 18 ans */
function validerDateNaissance() {
  const input = document.getElementById('ddn');
  if (!input.value) {
    afficherErreur('ddn', 'err-ddn', 'La date de naissance est obligatoire.');
    return false;
  }
  const naissance  = new Date(input.value);
  const aujourdhui = new Date();
  let age = aujourdhui.getFullYear() - naissance.getFullYear();
  const moisDiff = aujourdhui.getMonth() - naissance.getMonth();
  if (moisDiff < 0 || (moisDiff === 0 && aujourdhui.getDate() < naissance.getDate())) {
    age--;
  }
  if (isNaN(age) || age < 18) {
    afficherErreur('ddn', 'err-ddn', 'Vous devez avoir au moins 18 ans le jour du tirage.');
    return false;
  }
  effacerErreur('ddn', 'err-ddn');
  return true;
}

/* Email : format user@domaine.ext */
function validerEmail() {
  const val = document.getElementById('email').value.trim(); // trim() Supprime les espaces au début/fin. 
                                                             
  if (!val) {
    afficherErreur('email', 'err-email', "L'adresse e-mail est obligatoire.");
    return false;
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { //regex
    afficherErreur('email', 'err-email', 'Adresse e-mail invalide (ex : user@gmail.com).');
    return false;
  }
  effacerErreur('email', 'err-email');
  return true;
}

/* Téléphone algérien : 10 chiffres, commence par 05 / 06 / 07 */
function validerTelephone() {
  const val = document.getElementById('telephone').value.replace(/\s/g, '');
  if (!val) {
    afficherErreur('telephone', 'err-telephone', 'Le numéro de téléphone est obligatoire.');
    return false;
  }
  if (!/^0[567]\d{8}$/.test(val)) {
    afficherErreur('telephone', 'err-telephone',
      'Numéro invalide. Format attendu : 0555 36 47 88 (05, 06 ou 07).');
    return false;
  }
  effacerErreur('telephone', 'err-telephone');
  return true;
}

/* Wilaya : une option doit être sélectionnée */
function validerWilaya() {
  const val = document.getElementById('wilaya').value;
  if (!val) {
    afficherErreur('wilaya', 'err-wilaya', 'Veuillez sélectionner votre wilaya.');
    return false;
  }
  effacerErreur('wilaya', 'err-wilaya');
  return true;
}

/* Mot de passe : au moins 8 caractères */
function validerMotDePasse() {
  const val = document.getElementById('password').value;
  if (!val) {
    afficherErreur('password', 'err-password', 'Le mot de passe est obligatoire.');
    return false;
  }
  if (val.length < 8) {
    afficherErreur('password', 'err-password',
      'Le mot de passe doit contenir au moins 8 caractères.');
    return false;
  }
  effacerErreur('password', 'err-password');
  return true;
}

/* Confirmation : doit être identique au mot de passe */
function validerConfirmation() {
  const pwd  = document.getElementById('password').value;
  const conf = document.getElementById('confirm').value;
  if (!conf) {
    afficherErreur('confirm', 'err-confirm', 'Veuillez confirmer votre mot de passe.');
    return false;
  }
  if (conf !== pwd) {
    afficherErreur('confirm', 'err-confirm', 'Les mots de passe ne correspondent pas.');
    return false;
  }
  effacerErreur('confirm', 'err-confirm');
  return true;
}


   

function validerFormulaire() {
  const r1  = validerTexte('nom',       'err-nom',       'Le nom');
  const r2  = validerTexte('prenom',    'err-prenom',    'Le prénom');
  const r3  = validerNIN();
  const r4  = validerDateNaissance();
  const r5  = validerTexte('pere',      'err-pere',      'Le prénom du père');
  const r6  = validerTexte('grandpere', 'err-grandpere', 'Le prénom du grand-père');
  const r7  = validerTexte('mere',      'err-mere',      'Le nom et prénom de la mère');
  const r8  = validerEmail();
  const r9  = validerTelephone();
  const r10 = validerWilaya();
  const r11 = validerMotDePasse();
  const r12 = validerConfirmation();
  return r1 && r2 && r3 && r4 && r5 && r6 && r7 && r8 && r9 && r10 && r11 && r12;
}



document.addEventListener('DOMContentLoaded', function () { // attendre que la page soit prête

  //submition
  const form = document.getElementById('registerForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault(); // bloque l'envoi HTTP natif
      if (validerFormulaire()) {
        form.submit();  // ttb3t si tout est OK
      }
      // sinon : les erreurs stay 
    });
  }

  /* Validation en temps réel au blur (quand l'utilisateur quitte un champ) */
  const champsBlur = [
    { id: 'nom',       fn: () => validerTexte('nom',       'err-nom',       'Le nom') },
    { id: 'prenom',    fn: () => validerTexte('prenom',    'err-prenom',    'Le prénom') },
    { id: 'nin',       fn: validerNIN },
    { id: 'ddn',       fn: validerDateNaissance },
    { id: 'pere',      fn: () => validerTexte('pere',      'err-pere',      'Le prénom du père') },
    { id: 'grandpere', fn: () => validerTexte('grandpere', 'err-grandpere', 'Le prénom du grand-père') },
    { id: 'mere',      fn: () => validerTexte('mere',      'err-mere',      'Le nom et prénom de la mère') },
    { id: 'email',     fn: validerEmail },
    { id: 'telephone', fn: validerTelephone },
    { id: 'wilaya',    fn: validerWilaya },
    { id: 'password',  fn: validerMotDePasse },
    { id: 'confirm',   fn: validerConfirmation },
  ];

  champsBlur.forEach(function (champ) {
    const el = document.getElementById(champ.id);
    if (el) el.addEventListener('blur', champ.fn);
  });

  /* Le NIN n'accepte que les chiffres en temps réel */
  const ninInput = document.getElementById('nin');
  if (ninInput) {
    ninInput.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '');
    });
  }

  /* Re-valider la confirmation si le mot de passe change */
  const pwdInput = document.getElementById('password');
  if (pwdInput) {
    pwdInput.addEventListener('input', function () {
      const conf = document.getElementById('confirm').value;
      if (conf) validerConfirmation();
    });
  }

});
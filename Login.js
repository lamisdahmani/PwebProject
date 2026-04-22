

/*  afficher / effacer une erreur sur un champ ps drnah concept: DRY */


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


/*RÈGLES DE VALIDATION*/

/* NIN : exactement 18 chiffres */
function validerNIN() {
  const val = document.getElementById('nin').value.trim();
  if (!val) {
    afficherErreur('nin', 'err-nin', 'Le NIN est obligatoire.');
    return false;
  }
  if (!/^[0-9]{18}$/.test(val)) {
    afficherErreur('nin', 'err-nin', 'Le NIN doit comporter exactement 18 chiffres.');
    return false;
  }
  effacerErreur('nin', 'err-nin');
  return true;
}

/* Email : format user@domaine.ext */
function validerEmail() {
  const val = document.getElementById('email').value.trim();
  if (!val) {
    afficherErreur('email', 'err-email', "L'adresse e-mail est obligatoire.");
    return false;
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
    afficherErreur('email', 'err-email', 'Adresse e-mail invalide (ex : user@gmail.com).');
    return false;
  }
  effacerErreur('email', 'err-email');
  return true;
}

/* Mot de passe : champ non vide */
function validerMotDePasse() {
  const val = document.getElementById('password').value;
  if (!val) {
    afficherErreur('password', 'err-password', 'Le mot de passe est obligatoire.');
    return false;
  }
  effacerErreur('password', 'err-password');
  return true;
}


/*
   VALIDATION GLOBALE
   Variables séparées pour afficher TOUTES les erreurs ensemble.
 */

function validerFormulaire() {
  const r1 = validerNIN();
  const r2 = validerEmail();
  const r3 = validerMotDePasse();
  return r1 && r2 && r3;
}



document.addEventListener('DOMContentLoaded', function () {

  /* Soumission du formulaire */
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (validerFormulaire()) {
        form.submit();
      }
    });
  }

  /* Validation en temps réel au blur */    
  const nin   = document.getElementById('nin'); /*input se déclenche à chaque caractère tapé, */
                                                /* blur seulement quand on quitte. Pour le filtrage des non-chiffres,*/
                                                /* on veut l'effet immédiat : si l'utilisateur tape une lettre, elle disparaît instantanément*/
  const email = document.getElementById('email');
  const pwd   = document.getElementById('password');

  if (nin)   nin.addEventListener('blur', validerNIN);
  if (email) email.addEventListener('blur', validerEmail);
  if (pwd)   pwd.addEventListener('blur', validerMotDePasse);

  /* Le NIN n'accepte que les chiffres en temps réel */
  if (nin) {
    nin.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, ''); /*\D = tout caractère qui n'est PAS un chiffre (inverse de \d). g = global, remplace toutes les occurrences. On remplace par ''*/ 
                                                /* donc on efface les lettres au moment où elles sont saisies*/
    });
  }

});
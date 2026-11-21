// Parcours réels des deux pages, avec API et géocodage fictifs exclusivement.
// Une navigation ne perd ni la saisie ni les identifiants d'un cycle à reprendre.
const {test,expect}=require('@playwright/test');

const NOM='Alice Martin';
const JOUR='2026-10-26';
const TEMOIN={_id:'atelier-temoin',_n:1,date:JOUR,horaire:'14:00',ampm:'PM',
  duree:90,statut:'Planifié',conseiller:NOM,co_animateur:'',
  commune:'AGEN (47000)',lieu:'Salle témoin',thematique:'Atelier témoin',
  inscrits:4,presents:'',materiel:[],orienteur:'Partenaire fictif',public:'Adultes',
  residence:'',remarques:'',nb_ordinateurs:'',date_prelevement_materiel:'',date_retour_materiel:'',ouvert_a_tous:'Non'};
const VISIBILITE={saisie:true,historique:true,dashboard:true,agenda:true,
  calendrier:true,planning:true,carte:true,bingo:true,gestion_ordi:true};

async function preparer(page,entrees=[TEMOIN]){
  const etat={dialogues:[],accepter:false,ecritures:[],erreurs:[],partiel:false};
  page.on('pageerror',e=>etat.erreurs.push(e.message));
  page.on('console',message=>{
    if(message.type()==='error'&&!/Failed to load resource|net::ERR_|favicon|404/.test(message.text()))
      etat.erreurs.push(message.text());
  });
  page.on('dialog',async dialogue=>{
    etat.dialogues.push({type:dialogue.type(),message:dialogue.message()});
    if(etat.accepter)await dialogue.accept();else await dialogue.dismiss();
  });
  await page.clock.setFixedTime(new Date('2026-10-25T10:00:00Z'));
  await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.hostname==='127.0.0.1'||url.hostname==='localhost')return route.continue();
    if(url.hostname==='geo.api.gouv.fr')return route.fulfill({status:200,
      contentType:'application/json',body:JSON.stringify([{nom:'AGEN',codesPostaux:['47000']}])});
    if(url.hostname!=='ateliers-numeriques.alwaysdata.net')return route.abort();
    const action=url.searchParams.get('action');
    const params=new URLSearchParams(request.postData()||'');
    let rep={ok:true};
    if(action==='getComptes')rep={ok:true,comptes:[{conseiller:NOM,role:'admin',actif:'OUI'}]};
    else if(action==='checkPassword')rep={ok:true,role:'admin',conseiller:NOM,token:'a'.repeat(64)};
    else if(action==='getAll')rep={ok:true,entries:entrees,
      lists:{statuts:['Planifié','Réalisé','Annulé','Reporté','Non réalisé'],
        conseillers:[NOM],publics:['Adultes'],materiels:['Ordinateur']},
      visibility:VISIBILITE,config:{},conseillers_inactifs:[],conseiller_colors:{},
      colors:{},emails:{},materiels_masques:[],stockOrdinateurs:10};
    else if(action==='getVisibility')rep={ok:true,visibility:VISIBILITE};
    else if(action==='getConfig')rep={ok:true,config:{},maintenance:false};
    else if(action==='getTickets')rep={ok:true,tickets:[],moi:NOM};
    else if(action==='getLogs')rep={ok:true,logs:[]};
    else if(action==='jetonAvis')rep={ok:true,jeton:'b'.repeat(32),avis:{n:0,masque:false,seuil:3,attentes:null,clarte:null,aise_oui:0,autonomie_oui:0,remarques:[]},plafond:8,ouvert_du:JOUR,ouvert_au:'2026-11-09'};
    else if(action==='saveEntry'){
      etat.ecritures.push({action,entry:JSON.parse(params.get('entry'))});
    }else if(action==='lienAtelier'){
      // Liens Partage47 (AG-065) : réponse retenue tant que etat.retenue n'est pas libérée.
      if(etat.retenue)await etat.retenue;
      etat.liens=etat.liens||{};const id=params.get('_id');etat.liens[id]=etat.liens[id]||[];
      if(params.get('op')==='ajouter')etat.liens[id].push({titre:params.get('titre'),url:params.get('url')});
      else etat.liens[id]=etat.liens[id].filter(l=>l.url!==params.get('url'));
      rep={ok:true,liens:etat.liens[id]};
    }else if(action==='saveMany'){
      const entries=JSON.parse(params.get('entries'));
      etat.ecritures.push({action,entries});
      if(etat.partiel){
        etat.partiel=false;
        rep={ok:false,error:'Erreurs batch: [{"idx":1,"error":"Échec simulé"}]'};
      }
    }
    return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(rep)});
  });
  return etat;
}

async function connecter(page,fichier){
  if(fichier)await page.goto('/'+fichier);
  await expect(page.locator('input[type="password"]').first()).toBeVisible();
  await page.locator('select').first().selectOption(NOM);
  await page.locator('input[type="password"]').first().fill('mot-de-passe-fictif');
  await page.getByRole('button',{name:/Connexion/}).click();
  const acces=page.getByText('Accéder à mes ateliers',{exact:true});
  // Les deux applications accèdent normalement au profil choisi au login.
  // L'ancien écran de choix reste cependant un parcours valide de l'Index.
  if(await acces.isVisible().catch(()=>false)){
    await page.locator('select.accueil-select').selectOption(NOM);
    await acces.click();
  }
  await expect(page.getByRole('button',{name:'Nouveau',exact:true})).toBeVisible();
}

const formulaire=page=>page.locator('[data-saisie="1"]');
const lieu=page=>formulaire(page).getByPlaceholder('Salle, médiathèque, établissement…');
const remarques=page=>formulaire(page).getByPlaceholder('Notes libres');
const nav=(page,nom)=>page.getByRole('button',{name:nom,exact:true}).click();

async function mesurer(page){
  for(const width of [390,1280]){
    await page.setViewportSize({width,height:800});
    const dimensions=await page.evaluate(()=>({viewport:innerWidth,
      document:document.documentElement.scrollWidth}));
    expect(dimensions.document,JSON.stringify(dimensions)).toBeLessThanOrEqual(width+1);
  }
}

async function valeurs(champs,attendues){
  await expect.poll(()=>champs.evaluateAll(elements=>elements.map(el=>el.value))).toEqual(attendues);
}

async function ouvrirEdition(page){
  await nav(page,'Historique');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await page.getByRole('button',{name:'Éditer complet',exact:true}).click();
}

for(const fichier of ['index.html','admin.html']){
  test(fichier+' — saisie conservée, abandons confirmés, succès et déconnexion',async({page})=>{
    const etat=await preparer(page);
    await connecter(page,fichier);
    await nav(page,'Nouveau');
    await expect(formulaire(page)).toBeVisible();
    await formulaire(page).locator('input[type="date"]').first().fill('2026-11-03');
    await lieu(page).fill('Salle du brouillon');
    await remarques(page).fill('Contenu conservé pendant la consultation');
    await formulaire(page).getByRole('button',{name:/Saisie One Shot/}).click();
    await expect(lieu(page)).toHaveValue('Salle du brouillon');
    await mesurer(page);

    await nav(page,'Agenda');
    await expect(formulaire(page)).toBeHidden();
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('Salle du brouillon');
    await expect(remarques(page)).toHaveValue('Contenu conservé pendant la consultation');
    await expect(formulaire(page).locator('input[type="date"]').first()).toHaveValue('2026-11-03');
    expect(etat.dialogues).toHaveLength(0);

    await formulaire(page).getByRole('button',{name:/Réinitialiser/}).click();
    await expect(lieu(page)).toHaveValue('Salle du brouillon');
    expect(etat.dialogues).toHaveLength(1);
    etat.accepter=true;
    await formulaire(page).getByRole('button',{name:/Réinitialiser/}).click();
    await expect(lieu(page)).toHaveValue('');
    await expect(formulaire(page).locator('input[type="date"]').first()).toHaveValue('');

    await lieu(page).fill('Brouillon à préserver');
    etat.accepter=false;
    await ouvrirEdition(page);
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('Brouillon à préserver');
    const apresRefus=etat.dialogues.length;
    etat.accepter=true;
    await ouvrirEdition(page);
    await expect(lieu(page)).toHaveValue(TEMOIN.lieu);
    expect(etat.dialogues).toHaveLength(apresRefus+1);
    await lieu(page).fill('Salle corrigée');
    await formulaire(page).getByRole('button',{name:/Modifier l.atelier/}).click();
    await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveEntry').length).toBe(1);
    await expect(formulaire(page)).toBeHidden();
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('');
    await expect(formulaire(page).locator('input[type="date"]').first()).toHaveValue('');

    await lieu(page).fill('Ne doit pas survivre à la déconnexion');
    etat.accepter=false;
    await page.getByTitle('Déconnexion',{exact:true}).filter({visible:true}).first().click();
    await expect(lieu(page)).toHaveValue('Ne doit pas survivre à la déconnexion');
    etat.accepter=true;
    await page.getByTitle('Déconnexion',{exact:true}).filter({visible:true}).first().click();
    await expect(page.locator('input[type="password"]').first()).toBeVisible();
    await expect(formulaire(page)).toHaveCount(0);
    await connecter(page);
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('');
    expect(etat.erreurs).toEqual([]);
  });

  test(fichier+' — cycle conservé et reprise partielle avec les mêmes identifiants',async({page})=>{
    const etat=await preparer(page);
    await connecter(page,fichier);
    await nav(page,'Nouveau');
    await formulaire(page).getByRole('button',{name:/Saisie par cycle/}).click();
    await lieu(page).fill('Salle du cycle');
    await formulaire(page).getByPlaceholder("Nom de l'orienteur").fill('Partenaire fictif');
    await formulaire(page).getByPlaceholder('Code postal ou commune…').fill('AGEN');
    await formulaire(page).locator('.combo-item').filter({hasText:'AGEN'}).first().click();
    await formulaire(page).locator('select').filter({has:page.locator('option[value="'+NOM+'"]')}).first().selectOption(NOM);
    await formulaire(page).locator('select').filter({has:page.locator('option[value="Adultes"]')}).first().selectOption('Adultes');
    await formulaire(page).getByRole('radio',{name:'Oui',exact:true}).click();
    await formulaire(page).locator('input[type="date"]').nth(0).fill('2026-11-03');
    await formulaire(page).locator('input[type="time"]').nth(0).fill('09:00');
    await formulaire(page).getByPlaceholder('Thème de la séance').nth(0).fill('Séance A');
    await formulaire(page).locator('input[type="date"]').nth(1).fill('2026-11-10');
    await formulaire(page).locator('input[type="time"]').nth(1).fill('09:00');
    await formulaire(page).getByPlaceholder('Thème de la séance').nth(1).fill('Séance B');
    await formulaire(page).getByRole('button',{name:'Dupliquer cette séance',exact:true}).first().click();
    await formulaire(page).locator('input[type="time"]').nth(1).fill('10:30');
    const dates=['2026-11-03','2026-11-03','2026-11-10'];
    await valeurs(formulaire(page).locator('input[type="date"]'),dates);
    await formulaire(page).getByRole('button',{name:/Saisie par cycle/}).click();
    await valeurs(formulaire(page).locator('input[type="date"]'),dates);
    await mesurer(page);

    await nav(page,'Agenda');
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('Salle du cycle');
    await valeurs(formulaire(page).locator('input[type="date"]'),dates);
    await valeurs(formulaire(page).locator('input[type="time"]'),['09:00','10:30','09:00']);
    expect(etat.dialogues).toHaveLength(0);
    await formulaire(page).getByRole('button',{name:/Saisie One Shot/}).click();
    await valeurs(formulaire(page).locator('input[type="date"]'),dates);
    expect(etat.dialogues).toHaveLength(1);

    etat.accepter=true;
    etat.partiel=true;
    await formulaire(page).getByRole('button',{name:/Créer 3 atelier/}).click();
    await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveMany').length).toBe(1);
    await valeurs(formulaire(page).locator('input[type="date"]'),['2026-11-03']);
    const premier=etat.ecritures.find(e=>e.action==='saveMany').entries;
    // Ouvert à tous (AG-047) : un seul choix pour toutes les séances du cycle.
    expect(premier.map(e=>e.ouvert_a_tous)).toEqual(['Oui','Oui','Oui']);
    await nav(page,'Agenda');
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('Salle du cycle');
    await valeurs(formulaire(page).locator('input[type="time"]'),['10:30']);
    await formulaire(page).getByRole('button',{name:/Créer 1 atelier/}).click();
    await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveMany').length).toBe(2);
    const reprise=etat.ecritures.filter(e=>e.action==='saveMany')[1].entries;
    expect(reprise.map(e=>e._id)).toEqual([premier[1]._id]);
    await expect(formulaire(page)).toBeHidden();
    await nav(page,'Nouveau');
    await expect(lieu(page)).toHaveValue('');
    await valeurs(formulaire(page).locator('input[type="date"]'),['','']);
    await nav(page,'Calendrier');
    await page.getByRole('button',{name:'📥 Import ICS',exact:true}).click();
    await expect(formulaire(page).getByText('Choisir le fichier .ics',{exact:true})).toBeVisible();
    expect(etat.erreurs).toEqual([]);
  });
}

// AG-048 : les tuiles suivent le filtre « Ouvert à tous », comme les autres filtres.
test('historique — tuile Total : 2 ateliers, filtre Oui → 1',async({page})=>{
  const oui={...TEMOIN,_id:'atelier-oui',_n:2,thematique:'Atelier ouvert',ouvert_a_tous:'Oui'};
  const etat=await preparer(page,[TEMOIN,oui]);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  const total=page.locator('.kpi-histo b, .kpi-histo .v').first();
  await expect(total).toHaveText('2');
  await page.getByText('Filtres',{exact:true}).filter({visible:true}).first().click();
  // NEWGEN range le filtre dans la section « Public & commune » ; NextStep l'affiche directement.
  const section=page.getByText('Public & commune').filter({visible:true});
  if(await section.count())await section.first().click();
  const groupe=page.locator('div[aria-label="Ouvert à tous"]:not([role="radiogroup"])').filter({visible:true});
  await groupe.getByText('Oui',{exact:true}).click();
  await expect(total).toHaveText('1');
  await expect(page.getByText('Atelier ouvert',{exact:true}).filter({visible:true})).toHaveCount(1);
  await expect(page.getByText('Atelier témoin',{exact:true}).filter({visible:true})).toHaveCount(0);
  expect(etat.erreurs).toEqual([]);
});

// Statut groupé (05/11/2026) : la sélection passe en « Annulé » par un seul saveMany, liste Outlook proposée.
test('historique — sélection multiple : passer en Annulé, un seul saveMany',async({page})=>{
  const etat=await preparer(page);
  etat.accepter=true;
  await connecter(page,'index.html');
  await nav(page,'Historique');
  await page.getByRole('button',{name:'☑ Sélectionner plusieurs ateliers'}).filter({visible:true}).first().click();
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await page.getByRole('combobox',{name:'Nouveau statut'}).selectOption('Annulé');
  await page.getByRole('button',{name:/^Appliquer \(1\)$/}).click();
  await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveMany').length).toBe(1);
  const lot=etat.ecritures.find(e=>e.action==='saveMany').entries;
  expect(lot.map(e=>[e._id,e.statut])).toEqual([['atelier-temoin','Annulé']]);
  await expect(page.getByRole('button',{name:/Copier la liste à supprimer dans Outlook/})).toBeVisible();
  expect(etat.erreurs).toEqual([]);
});

// Volet Détail (05/11/2026) : commune, lieu, orienteur, conseiller et co-animateur modifiables sur place.
test('historique — volet : lieu modifié sur place et enregistré',async({page})=>{
  const etat=await preparer(page);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  const lieuVolet=page.getByPlaceholder('Salle, médiathèque, établissement…').filter({visible:true}).last();
  await expect(lieuVolet).toHaveValue(TEMOIN.lieu);
  await lieuVolet.fill('Salle modifiée dans le volet');
  await page.getByRole('button',{name:/Enregistrer/}).filter({visible:true}).last().click();
  await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveEntry').length).toBe(1);
  const e=etat.ecritures.find(x=>x.action==='saveEntry').entry;
  expect([e.lieu,e.commune,e.conseiller]).toEqual(['Salle modifiée dans le volet',TEMOIN.commune,TEMOIN.conseiller]);
  expect(etat.erreurs).toEqual([]);
});


// Vérification de Codex sur AG-065 (19/11/2026) : la réponse tardive d'un lien de A ne remplit pas le
// volet de B, et ne rétablit pas les autres champs de A modifiés entre-temps.
test('historique — volet : réponse tardive d\'un lien, autre atelier ouvert et lieu modifié entre-temps',async({page})=>{
  const B={...TEMOIN,_id:'atelier-b',_n:2,thematique:'Atelier fictif B',lieu:'Salle B'};
  const etat=await preparer(page,[TEMOIN,B]);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  const volet=page.locator('.side-panel.open');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  let liberer;etat.retenue=new Promise(r=>{liberer=r;});
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await volet.getByPlaceholder('Titre (ex. Résultats PIX)').fill('Document fictif A');
  await volet.getByPlaceholder('https://partage.lotetgaronne.fr/…').fill('https://partage.lotetgaronne.fr/index.php/s/fictifA');
  await volet.getByRole('button',{name:'+ Ajouter'}).click();
  await volet.getByRole('tab',{name:'📋 Détail'}).click();
  await volet.getByPlaceholder('Salle, médiathèque, établissement…').fill('Salle modifiée pendant le lien');
  await volet.getByRole('button',{name:/Enregistrer/}).click();
  await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveEntry').length).toBe(1);
  await expect(volet).toHaveCount(0);   // « Enregistrer » ferme le volet
  await page.getByText('Atelier fictif B',{exact:true}).filter({visible:true}).first().click();
  await expect(volet.getByRole('tab',{name:'📋 Détail'})).toHaveAttribute('aria-selected','true');   // chaque atelier ouvre sur Détail
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await expect(volet.getByText('📎 Liens Partage47')).toBeVisible();
  etat.retenue=null;liberer();
  await expect(page.getByText('✅ Lien ajouté')).toBeVisible();
  await expect(volet.getByRole('link',{name:/Document fictif A/})).toHaveCount(0);
  await volet.locator('button').first().click();
  await expect(page.getByText('Salle modifiée pendant le lien').filter({visible:true}).first()).toBeVisible();
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await expect(volet.getByRole('tab',{name:'📋 Détail'})).toHaveAttribute('aria-selected','true');   // B fermé sur Reporting
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await expect(volet.getByRole('link',{name:/Document fictif A/})).toBeVisible();
  expect(etat.erreurs).toEqual([]);
});

// Duplication : le doublon part sans les liens de l'atelier source, à l'envoi comme à l'écran.
test('historique — dupliquer un atelier qui a un lien : le doublon n\'en a aucun',async({page})=>{
  const SOURCE={...TEMOIN,liens:[{titre:'Document source',url:'https://partage.lotetgaronne.fr/index.php/s/source'}]};
  const etat=await preparer(page,[SOURCE]);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  const volet=page.locator('.side-panel.open');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await expect(volet.getByRole('link',{name:/Document source/})).toBeVisible();
  await volet.getByRole('button',{name:/Dupliquer/}).click();
  await formulaire(page).locator('input[type="date"]').first().fill('2026-10-28');
  await formulaire(page).locator('input[type="time"]').first().fill('09:00');
  await formulaire(page).getByRole('button',{name:/Enregistrer l'atelier/}).click();
  await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveEntry').length).toBe(1);
  const e=etat.ecritures.find(x=>x.action==='saveEntry').entry;
  expect(e.liens||[]).toEqual([]);
  // Après création, l'Historique montre le doublon (28/10) mis en évidence : son volet n'a aucun lien.
  // (Pas de getByText('28') : la pastille des Nouveautés peut afficher « 28 », constaté en CI le 20/11/2026.)
  const cartes=page.getByText('Atelier témoin',{exact:true}).filter({visible:true});
  await expect(cartes).toHaveCount(1);
  await cartes.first().click();
  await expect(volet.getByPlaceholder('Salle, médiathèque, établissement…')).toHaveValue(TEMOIN.lieu);
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await expect(volet.getByText('📎 Liens Partage47')).toBeVisible();
  await expect(volet.getByText('Aucun lien.')).toBeVisible();
  await expect(volet.getByRole('link',{name:/Document source/})).toHaveCount(0);
  expect(etat.erreurs).toEqual([]);
});

// Onglets du volet (20/11/2026) : le passage en « Réalisé » avec « Remplir maintenant » ouvre l'onglet
// Reporting sur la fiche bilan, sans rien enregistrer ; le QR code des avis y est aussi.
test('historique — volet : « Remplir maintenant » ouvre l\'onglet Reporting sur la fiche bilan',async({page})=>{
  const etat=await preparer(page);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  const volet=page.locator('.side-panel.open');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await expect(volet.getByRole('button',{name:/QR code des avis/})).toHaveCount(0);   // plus dans Détail
  // Atelier encore « Planifié » : l'onglet Reporting et le QR code sont déjà accessibles (avis pendant l'atelier,
  // statut souvent changé le lendemain ou plus tard, demande de l'utilisateur du 20/11/2026).
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await expect(volet.getByRole('button',{name:/QR code des avis/})).toBeVisible();
  await expect(volet.getByText('📝 Bilan de l\'atelier')).toBeVisible();
  await volet.getByRole('tab',{name:'📋 Détail'}).click();
  await volet.locator('select').filter({has:page.locator('option[value="Réalisé"]')}).first().selectOption('Réalisé');
  await volet.getByRole('button',{name:/Enregistrer/}).click();
  await page.getByRole('button',{name:/Remplir maintenant/}).click();
  await expect(volet.getByRole('tab',{name:'📊 Reporting'})).toHaveAttribute('aria-selected','true');
  await expect(volet.getByText('📝 Bilan de l\'atelier')).toBeVisible();
  await expect(volet.getByRole('button',{name:/QR code des avis/})).toBeVisible();
  expect(etat.ecritures.filter(e=>e.action==='saveEntry')).toHaveLength(0);
  expect(etat.erreurs).toEqual([]);
});

// Fiche bilan remplissable avant « Réalisé » (20/11/2026) : elle part avec l'atelier encore « Planifié ».
test('historique — volet : fiche bilan remplie sur un atelier « Planifié », enregistrée',async({page})=>{
  const etat=await preparer(page);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  const volet=page.locator('.side-panel.open');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await volet.getByRole('button',{name:'Débutant',exact:true}).click();
  await volet.getByRole('button',{name:/Enregistrer/}).click();
  await expect.poll(()=>etat.ecritures.filter(e=>e.action==='saveEntry').length).toBe(1);
  const e=etat.ecritures.find(x=>x.action==='saveEntry').entry;
  const fiche=typeof e.fiche_bilan==='string'?JSON.parse(e.fiche_bilan||'{}'):e.fiche_bilan;
  expect([e.statut,fiche&&fiche.niveau]).toEqual(['Planifié','Débutant']);
  expect(etat.erreurs).toEqual([]);
});

// Téléphone (20/11/2026) : la fenêtre du QR s'ouvre AU-DESSUS du volet ; un clic dehors la ferme, puis un clic
// dehors ferme le volet (le volet avait été remonté au premier plan le 19/11 et la cachait).
test('téléphone — volet : QR ouvert au-dessus, fermé par un clic dehors, puis volet fermé par un clic dehors',async({page})=>{
  await page.setViewportSize({width:390,height:760});
  const etat=await preparer(page);
  await connecter(page,'index.html');
  // À 390 px, le message d'accueil couvre la barre du bas : clic transmis directement au bouton.
  await page.getByRole('button',{name:'Historique',exact:true}).dispatchEvent('click');
  const volet=page.locator('.side-panel.open');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await volet.getByRole('button',{name:/QR code des avis/}).click();
  const titre=page.getByText('📱 Avis des stagiaires',{exact:true});
  await expect(titre).toBeVisible();
  await expect(volet).toHaveCount(1);
  expect(await titre.evaluate(t=>{const r=t.getBoundingClientRect();const e=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return e===t||t.contains(e);})).toBe(true);
  await page.mouse.click(8,300);   // fond de la fenêtre du QR, hors de sa boîte
  await expect(titre).toHaveCount(0);
  await expect(volet).toHaveCount(1);
  await page.mouse.click(20,300);   // voile du volet, à gauche du volet
  await expect(volet).toHaveCount(0);
  expect(etat.erreurs).toEqual([]);
});

// Limite de liens affichée (20/11/2026) : à 5 liens, plus de champ d'ajout et la raison est dite (règle 65).
test('historique — volet : 5 liens, maximum atteint dit à l\'écran',async({page})=>{
  const PLEIN={...TEMOIN,liens:[1,2,3,4,5].map(i=>({titre:'Document '+i,url:'https://partage.lotetgaronne.fr/index.php/s/doc'+i}))};
  const etat=await preparer(page,[PLEIN]);
  await connecter(page,'index.html');
  await nav(page,'Historique');
  const volet=page.locator('.side-panel.open');
  await page.getByText('Atelier témoin',{exact:true}).filter({visible:true}).first().click();
  await volet.getByRole('tab',{name:'📊 Reporting'}).click();
  await expect(volet.getByText('5 liens sur 5 maximum par atelier')).toBeVisible();
  await expect(volet.getByText('Maximum atteint (5 liens) : retirez-en un pour en ajouter un autre.')).toBeVisible();
  await expect(volet.getByRole('button',{name:'+ Ajouter'})).toHaveCount(0);
  expect(etat.erreurs).toEqual([]);
});

// Pastille « nouveaux ateliers » retenue d'une visite à l'autre (20/11/2026) : numéro _n le plus grand déjà vu,
// par page et par personne, dans le navigateur.
const AUTRE={...TEMOIN,_id:'atelier-autre',_n:5,conseiller:'Eva Fictive',thematique:'Atelier ajouté par une collègue'};
const CLE_VUS='ateliers_dernier_n:/:'+NOM+':2026';   // une mémoire par année (AG-066, Codex, 21/11/2026)
test('pastille — atelier ajouté par une collègue depuis la dernière visite signalé, puis retenu comme vu',async({page})=>{
  await page.addInitScript(cle=>{try{if(!sessionStorage.getItem('init-vus')){localStorage.setItem(cle,'1');sessionStorage.setItem('init-vus','1');}}catch(_){}},CLE_VUS);
  const etat=await preparer(page,[TEMOIN,AUTRE]);
  await connecter(page,'index.html');
  const pastille=page.locator('.topbar-notif-btn');
  await expect(pastille).toHaveText(/1/);
  await pastille.click();
  await expect(pastille).toHaveCount(0);
  expect(await page.evaluate(cle=>localStorage.getItem(cle),CLE_VUS)).toBe('5');
  expect(etat.erreurs).toEqual([]);
});
test('pastille — première visite : rien de signalé, point de départ noté',async({page})=>{
  const etat=await preparer(page,[TEMOIN,AUTRE]);
  await connecter(page,'index.html');
  await expect(page.getByRole('button',{name:'Nouveau',exact:true})).toBeVisible();
  await page.waitForTimeout(500);
  await expect(page.locator('.topbar-notif-btn')).toHaveCount(0);
  expect(await page.evaluate(cle=>localStorage.getItem(cle),CLE_VUS)).toBe('5');
  expect(etat.erreurs).toEqual([]);
});

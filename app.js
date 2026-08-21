// ── App Frontend (conseillers) v10.0 ─────────────────────────
var VIEW_META_F = {
  saisie:     {ico:'✏️',  label:'Nouveau',      group:'Action'},
  historique: {ico:'📋',  label:'Historique',   group:'Voir'},
  agenda:     {ico:'🗓️', label:'Agenda',        group:'Voir'},
  calendrier: {ico:'📅',  label:'Calendrier',   group:'Voir'},
  carte:      {ico:'🗺️', label:'Carte',        group:'Voir'},
  roadmap:    {ico:'🛣️', label:'Roadmap',      group:'Voir'},
  graphiques: {ico:'📊',  label:'Statistiques', group:'Stats'},
  bingo:      {ico:'🎯',  label:'Bingo',        group:'Stats'},
};

function MaintenanceScreen({msg}){
  return CE('div',{style:{display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',minHeight:'100vh',background:'var(--bg)',fontFamily:"'Segoe UI',sans-serif",textAlign:'center',gap:12}},
    CE('div',{style:{background:'var(--surface)',borderRadius:14,padding:'40px 48px',boxShadow:'var(--shadow-panel)',maxWidth:420,width:'90%'}},
      CE('div',{style:{fontSize:52,marginBottom:12}},'🔧'),
      CE('div',{style:{fontSize:22,fontWeight:800,color:'var(--info)',marginBottom:8}},'Maintenance en cours'),
      CE('div',{style:{fontSize:13,color:'var(--text-2)',lineHeight:1.6,marginBottom:20}},msg||"L'application est temporairement indisponible. Merci de votre patience."),
      CE('div',{style:{display:'inline-block',background:'#fef9c3',color:'#92400e',fontSize:12,fontWeight:700,padding:'4px 14px',borderRadius:20,border:'1px solid #fcd34d'}},'⏳ Mise à jour en cours'),
      CE('div',{style:{fontSize:12,color:'var(--text-3)',marginTop:16}},'Contactez l\'administrateur pour plus d\'infos.')
    )
  );
}

// ── VueAccueilStatic — dropdown CONUM fixe (landing, post-authentification) ─
function VueAccueilStatic({onChoix,conseillers}){
  const CONUM_STATIC = conseillers&&conseillers.length?conseillers:[...CONSEILLERS_DEFAULT];
  const[choix,setChoix]=React.useState('');
  return CE('div',{className:'accueil-wrap'},
    CE('div',{className:'accueil-card'},
      CE('div',{className:'accueil-logo'},'🖥️'),
      CE('div',{className:'accueil-title'},'Ateliers Inclusion Numérique — NewGen'),
      CE('div',{className:'accueil-sub'},'Conseil Départemental du Lot-et-Garonne'),
      CE('label',{className:'accueil-label'},'Qui êtes-vous ?'),
      CE('select',{className:'accueil-select',value:choix,onChange:e=>setChoix(e.target.value)},
        CE('option',{value:''},'— Sélectionner votre nom —'),
        CONUM_STATIC.map(c=>CE('option',{key:c,value:c},c))
      ),
      CE('button',{className:'accueil-btn',disabled:!choix,onClick:()=>onChoix(choix)},'📋 Accéder à mes ateliers')
    )
  );
}

// Miroir JS de defaultPwd() côté GAS — formule publique (cd47+prénom), pas un
// secret : sert uniquement à détecter côté client qu'un conum utilise encore
// son mot de passe de création, pour déclencher le changement obligatoire.
function defaultPwdIndex(nom){
  var p=(nom||'').split(' ')[0]||nom||'';
  p=p.toLowerCase().replace(/[àâä]/g,'a').replace(/[éèêë]/g,'e').replace(/[îï]/g,'i').replace(/[ôö]/g,'o').replace(/[ùûü]/g,'u').replace(/ç/g,'c');
  return 'cd47'+p;
}

// ── VueLoginIndex — gate mot de passe par conum (identification, avant l'accueil) ─
function VueLoginIndex({conseillers,onSuccess}){
  const MAX_FAILS=3, LOCK_MS=5*60*1000;
  const base=conseillers&&conseillers.length?conseillers:CONSEILLERS_DEFAULT;

  const[conseiller,setConseiller]=React.useState(base[0]||'');
  const[pwd,setPwd]=React.useState('');
  const[show,setShow]=React.useState(false);
  const[err,setErr]=React.useState('');
  const[loading,setLoading]=React.useState(false);
  const[failCount,setFailCount]=React.useState(0);
  const[lockUntil,setLockUntil]=React.useState(0);
  const[countdown,setCountdown]=React.useState(0);

  // Mot de passe par défaut détecté à la connexion → changement obligatoire
  // avant d'entrer, tant que pendingRes n'est pas encore transmis à onSuccess.
  const[mustChangePwd,setMustChangePwd]=React.useState(false);
  const[pendingRes,setPendingRes]=React.useState(null);
  const[newPwd,setNewPwd]=React.useState('');
  const[newPwd2,setNewPwd2]=React.useState('');
  const[newPwdErr,setNewPwdErr]=React.useState('');
  const[changingPwd,setChangingPwd]=React.useState(false);
  const[showNewPwd,setShowNewPwd]=React.useState(false);

  React.useEffect(()=>{ if(base.length) setConseiller(c=>base.includes(c)?c:base[0]); },[base.join(',')]);

  React.useEffect(()=>{
    if(!lockUntil||lockUntil<=Date.now())return;
    const tick=()=>{
      const left=lockUntil-Date.now();
      if(left<=0){setCountdown(0);setLockUntil(0);setFailCount(0);}
      else setCountdown(Math.ceil(left/1000));
    };
    tick();
    const id=setInterval(tick,1000);
    return()=>clearInterval(id);
  },[lockUntil]);

  const isLocked=lockUntil>Date.now()||countdown>0;

  async function handleSubmit(){
    if(!pwd.trim()||isLocked)return;
    setLoading(true);setErr('');
    try{
      const res=await apiFetch('checkPassword',{conseiller,password:pwd,userAgent:navigator.userAgent,source:'index.html'});
      if(res.ok){
        setFailCount(0);setLockUntil(0);
        if(pwd.trim()===defaultPwdIndex(conseiller)){
          setPendingRes(res);
          setMustChangePwd(true);
        }else{
          onSuccess(conseiller,res);
        }
      }else{
        const nf=failCount+1;
        setFailCount(nf);
        const raison=res.error||'Mot de passe incorrect';
        if(nf>=MAX_FAILS){
          setLockUntil(Date.now()+LOCK_MS);
          setErr('🔒 Trop de tentatives — accès bloqué 5 minutes.');
        }else{
          setErr(`${raison} (${nf}/${MAX_FAILS} tentative${nf>1?'s':''})`);
        }
      }
    }catch(e){setErr('Erreur réseau : '+e.message);}
    finally{setLoading(false);}
  }

  async function handleChangePwd(){
    if(!pwdPolicyOk(newPwd)){setNewPwdErr(PWD_POLICY_MSG);return;}
    if(newPwd!==newPwd2){setNewPwdErr('Les mots de passe ne correspondent pas');return;}
    setChangingPwd(true);setNewPwdErr('');
    try{
      const res2=await apiFetch('selfSetPassword',{password:newPwd,token:pendingRes.token});
      if(res2&&res2.ok){
        onSuccess(conseiller,pendingRes);
      }else{
        setNewPwdErr(res2&&res2.error||'Erreur');
      }
    }catch(e){setNewPwdErr('Erreur réseau : '+e.message);}
    finally{setChangingPwd(false);}
  }

  const mins=Math.floor(countdown/60), secs=String(countdown%60).padStart(2,'0');

  return CE('div',{className:'accueil-wrap'},
    CE('div',{className:'accueil-card'},
      CE('div',{className:'accueil-logo'},'🖥️'),
      CE('div',{className:'accueil-title'},'Ateliers Inclusion Numérique — NewGen'),
      CE('div',{className:'accueil-sub'},'Conseil Départemental du Lot-et-Garonne'),
      isLocked
        ? CE('div',{style:{textAlign:'center',padding:'28px 0'}},
            CE('div',{style:{fontSize:44,marginBottom:10}},'🔒'),
            CE('div',{style:{fontSize:15,fontWeight:700,color:'#c53030',marginBottom:6}},'Accès temporairement bloqué'),
            CE('div',{style:{fontSize:28,fontWeight:800,color:'#1a202c',fontVariantNumeric:'tabular-nums'}},mins+'m'+secs+'s'),
            CE('div',{style:{fontSize:12,color:'#9ca3af',marginTop:4}},'Trop de tentatives incorrectes')
          )
        : mustChangePwd
        ? CE(React.Fragment,null,
            CE('div',{style:{textAlign:'center',fontSize:32,marginBottom:8}},'🔑'),
            CE('div',{style:{fontSize:14,fontWeight:700,color:'#1a202c',textAlign:'center',marginBottom:4}},'Mot de passe par défaut détecté'),
            CE('div',{style:{fontSize:12,color:'#718096',textAlign:'center',marginBottom:4}},'Choisissez un nouveau mot de passe personnel pour continuer.'),
            CE('div',{style:{fontSize:11,color:'#a0aec0',textAlign:'center',marginBottom:12}},'12 caractères min. avec majuscule, minuscule, chiffre et caractère spécial.'),
            CE('div',{style:{position:'relative',margin:'0 0 10px'}},
              CE('input',{
                type:showNewPwd?'text':'password',placeholder:'Nouveau mot de passe',value:newPwd,
                onChange:e=>setNewPwd(e.target.value),
                style:{width:'100%',padding:'10px 40px 10px 14px',border:'1px solid var(--border)',borderRadius:8,fontSize:14,outline:'none',boxSizing:'border-box',background:'var(--surface)',color:'var(--text)'}
              }),
              CE('button',{onClick:()=>setShowNewPwd(s=>!s),style:{position:'absolute',right:10,top:'50%',transform:'translateY(-50%)',background:'none',border:'none',cursor:'pointer',fontSize:16,color:'#718096',padding:0}},showNewPwd?'🙈':'👁️')
            ),
            CE('div',{style:{position:'relative',margin:'0 0 10px'}},
              CE('input',{
                type:showNewPwd?'text':'password',placeholder:'Confirmer',value:newPwd2,
                onChange:e=>setNewPwd2(e.target.value),
                onKeyDown:e=>e.key==='Enter'&&handleChangePwd(),
                style:{width:'100%',padding:'10px 40px 10px 14px',border:'1px solid var(--border)',borderRadius:8,fontSize:14,outline:'none',boxSizing:'border-box',background:'var(--surface)',color:'var(--text)'}
              }),
              CE('button',{onClick:()=>setShowNewPwd(s=>!s),style:{position:'absolute',right:10,top:'50%',transform:'translateY(-50%)',background:'none',border:'none',cursor:'pointer',fontSize:16,color:'#718096',padding:0}},showNewPwd?'🙈':'👁️')
            ),
            newPwdErr&&CE('p',{style:{color:'#c53030',fontSize:13,marginBottom:8}},newPwdErr),
            CE('button',{className:'accueil-btn',disabled:changingPwd||!newPwd||!newPwd2,onClick:handleChangePwd},changingPwd?'Enregistrement…':'✅ Valider et continuer')
          )
        : CE(React.Fragment,null,
            CE('label',{className:'accueil-label'},'Qui êtes-vous ?'),
            CE('select',{className:'accueil-select',value:conseiller,onChange:e=>setConseiller(e.target.value)},
              base.map(c=>CE('option',{key:c,value:c},c))
            ),
            CE('div',{style:{position:'relative',margin:'10px 0'}},
              CE('input',{
                type:show?'text':'password',placeholder:'Mot de passe',value:pwd,
                onChange:e=>setPwd(e.target.value),
                onKeyDown:e=>e.key==='Enter'&&handleSubmit(),
                style:{width:'100%',padding:'10px 40px 10px 14px',border:'1px solid var(--border)',borderRadius:8,fontSize:14,outline:'none',boxSizing:'border-box',background:'var(--surface)',color:'var(--text)'}
              }),
              CE('button',{onClick:()=>setShow(s=>!s),style:{position:'absolute',right:10,top:'50%',transform:'translateY(-50%)',background:'none',border:'none',cursor:'pointer',fontSize:16,color:'#718096',padding:0}},show?'🙈':'👁️')
            ),
            err&&CE('p',{style:{color:'#c53030',fontSize:13,marginBottom:8}},err),
            CE('button',{className:'accueil-btn',disabled:loading||!pwd.trim(),onClick:handleSubmit},loading?'Vérification…':'🔓 Connexion')
          )
    )
  );
}

function App(){
  const[authed,setAuthed]          = React.useState(()=>!!window.authToken.get());
  const[view,setView]              = React.useState('accueil');
  const[entries,setEntries]        = React.useState([]);
  const[loading,setLoading]        = React.useState(true);
  const[error,setError]            = React.useState(null);
  const[maintenance,setMaintenance]= React.useState(null); // null=checking, false=off, {msg}=on
  const[newEntries,setNewEntries]   = React.useState([]);
  const[seenIds,setSeenIds]        = React.useState(new Set());
  const[filtreConseiller,setFiltreConseiller] = React.useState(null);
  const[editingId,setEditingId]    = React.useState(null);
  const[prefillData,setPrefillData] = React.useState(null);
  const[annee,setAnneeState]       = React.useState(()=>localStorage.getItem('f_annee')||String(new Date().getFullYear()));
  const[visibility,setVisibility]   = React.useState({saisie:true,historique:true,dashboard:true,carte:true,bingo:true,calendrier:false,agenda:false,roadmap:false});
  const[lists,setLists]            = React.useState({
    statuts:[...STATUTS_DEFAULT],conseillers:[...CONSEILLERS_DEFAULT],
    publics:[...PUBLICS_DEFAULT],materiels:[...MATERIELS_DEFAULT]
  });
  const[lastSync,setLastSync]      = React.useState(null);
  const[online,setOnline]          = React.useState(navigator.onLine);
  const[showPicker,setShowPicker]   = React.useState(false);
  const[inactifsSet,setInactifsSet] = React.useState(new Set());
  const[sidebarPinned,setSidebarPinned] = React.useState(()=>localStorage.getItem('sidebar_pinned')==='1');
  const[darkMode,setDarkMode]=React.useState(()=>localStorage.getItem('f_dark')==='1');
  React.useEffect(()=>{document.documentElement.setAttribute('data-theme',darkMode?'dark':'light');localStorage.setItem('f_dark',darkMode?'1':'0');},[darkMode]);

  // Un token peut déjà être présent en sessionStorage au chargement (login
  // précédent dans cet onglet, ou déjà connecté sur admin.html — sessionStorage
  // est partagé entre les pages d'une même origine et survit à un simple
  // rechargement). Dans ce cas authed démarre déjà à true et VueLoginIndex ne
  // s'affiche jamais : sans ce useEffect on retombait sur l'ancien écran
  // "Qui êtes-vous ?" (VueAccueilStatic) au lieu de rester identifié.
  React.useEffect(()=>{
    if(authed&&!filtreConseiller){
      const stored=sessionStorage.getItem('gs_conseiller');
      if(stored) handleChoixConseiller(stored);
    }
  },[]);

  // ── Helpers ───────────────────────────────────────────────────
  function setAnnee(v){ localStorage.setItem('f_annee',v); setAnneeState(v); }
  function resetConseiller(){ setFiltreConseiller(null); }
  function handleLogout(){
    if(!window.confirm('Se déconnecter ?'))return;
    window.authToken.clear();
    setAuthed(false);
    setFiltreConseiller(null);
    setShowPicker(false);
    setView('accueil');
  }
  function togglePin(){ setSidebarPinned(p=>{ const n=!p; localStorage.setItem('sidebar_pinned',n?'1':'0'); return n; }); }

  const isFirstLoad=React.useRef(true);
  const errorRef=React.useRef(null);
  errorRef.current=error;

  // ── Chargement v11.0 — fetchAll single-flight + cache localStorage ─
  async function loadData(attempt=1, silent=false){
    if(!silent) setLoading(true);
    setError(null);

    // ── Cache localStorage : afficher les données précédentes immédiatement ──
    if(!silent && attempt === 1){
      const cacheKey = `ateliers_cache_${annee}`;
      try{
        const cached = localStorage.getItem(cacheKey);
        if(cached){
          const {entries:cachedEntries, lists:cachedLists} = JSON.parse(cached);
          if(cachedEntries) setEntries(cachedEntries);
          if(cachedLists){ setLists(cachedLists);STATUTS=[...cachedLists.statuts];CONSEILLERS=[...cachedLists.conseillers];PUBLICS=[...cachedLists.publics];MATERIELS=[...cachedLists.materiels]; }
        }
      }catch(_){}
    }

    try{
      // fetchAll porte seul les tentatives (3 essais échelonnés, budget borné).
      const data=await fetchAll(annee,{force:true});
      const incoming = data.entries||[];
      setEntries(incoming);
      if(data.lists){
        const l=data.lists;
        const nl={
          statuts:(Array.isArray(l.statuts)&&l.statuts.length)?l.statuts:[...STATUTS_DEFAULT],
          conseillers:(Array.isArray(l.conseillers)&&l.conseillers.length)?l.conseillers:[...CONSEILLERS_DEFAULT],
          publics:(Array.isArray(l.publics)&&l.publics.length)?l.publics:[...PUBLICS_DEFAULT],
          materiels:(Array.isArray(l.materiels)&&l.materiels.length)?l.materiels:[...MATERIELS_DEFAULT]
        };
        setLists(nl);STATUTS=[...nl.statuts];CONSEILLERS=[...nl.conseillers];PUBLICS=[...nl.publics];MATERIELS=[...nl.materiels];
        // Mettre à jour le cache
        try{ localStorage.setItem(`ateliers_cache_${annee}`, JSON.stringify({entries:incoming,lists:nl})); }catch(_){}
      }
      if(data.visibility) setVisibility(v=>({...v,...data.visibility}));
      if(data.conseiller_colors) applyColors(data.conseiller_colors);
      setLastSync(new Date());
      setSeenIds(prev=>{
        if(prev.size===0) return new Set(incoming.map(e=>e._id));
        const nouvs=incoming.filter(e=>!prev.has(e._id));
        if(nouvs.length>0) setNewEntries(n=>[...nouvs,...n]);
        return new Set(incoming.map(e=>e._id));
      });
      setLoading(false);
    }catch(err){
      // fetchAll a déjà épuisé ses tentatives : on affiche, sans relancer.
      setError('Impossible de charger : '+err.message);
      setLoading(false);
    }
  }

  React.useEffect(()=>{
    const on=()=>setOnline(true);
    const off=()=>setOnline(false);
    window.addEventListener('online',on);
    window.addEventListener('offline',off);
    return()=>{ window.removeEventListener('online',on); window.removeEventListener('offline',off); };
  },[]);

  React.useEffect(()=>{
    if(!showPicker)return;
    const close=()=>setShowPicker(false);
    document.addEventListener('mousedown',close);
    return()=>document.removeEventListener('mousedown',close);
  },[showPicker]);

  React.useEffect(()=>{loadCommunes47().catch(()=>{});},[]);

  // getAll part dès le montage, en parallèle de getComptes/getConfig
  // ci-dessous. Un chaînage séquentiel a été essayé puis retiré : sa
  // justification ("GAS n'exécute qu'une requête à la fois par projet")
  // n'a pas résisté aux logs Exécutions Apps Script (deux doGet observés se
  // chevauchant dans le temps) — et le chaînage a un risque asymétrique
  // (si getAll traîne ou échoue, getComptes/getConfig n'ont plus leur
  // chance de réussir en parallèle pendant ce temps). Reste en parallèle
  // tant qu'aucune preuve ne justifie de les enchaîner.
  React.useEffect(()=>{
    if(isFirstLoad.current){isFirstLoad.current=false;loadData();}
    else{setSeenIds(new Set());loadData();}
  },[annee]);

  React.useEffect(()=>{
    apiFetch('getComptes').then(res=>{if(res.ok&&res.comptes){setInactifsSet(new Set(res.comptes.filter(c=>c.actif==='NON').map(c=>c.conseiller)));}}).catch(()=>{});
  },[]);

  // Check maintenance : ne bloque pas l'affichage de la landing. fetchConfig
  // (au lieu d'apiFetch('getConfig') brut) dédoublonne avec les autres
  // composants qui demandent la même config — ce gain-là est indépendant de
  // la question du chaînage ci-dessus, donc conservé.
  React.useEffect(()=>{
    fetchConfig().then(res=>{
      if(res.ok&&res.config){
        const active=res.config['maintenance']==='true'||res.config['maintenance']===true||res.config['maintenance']==='TRUE';
        const msg=res.config['maintenance_msg']||'';
        setMaintenance(active?{msg}:false);
      } else setMaintenance(false);
    }).catch(()=>setMaintenance(false));
  },[]);

  // Onglet caché = pas d'appel : un onglet Index oublié en arrière-plan
  // ne doit pas ajouter de getAll superflu toutes les 5 min pour rien.
  React.useEffect(()=>{
    const id=setInterval(()=>{ if(document.visibilityState==='visible'&&!errorRef.current) loadData(1,true); },5*60*1000);
    return()=>clearInterval(id);
  },[annee]);

  React.useEffect(()=>{
    const label=view==='accueil'?'Accueil':VIEW_META_F[view]?.label||view;
    document.title=`${label} — Ateliers Inclusion Numérique`;
  },[view]);

  // ── Handlers ──────────────────────────────────────────────────
  function handleChoixConseiller(nom){
    setFiltreConseiller(nom);
    setShowPicker(false);
    setView(visibility.historique?'historique':visibility.calendrier?'calendrier':visibility.saisie?'saisie':'dashboard');
    if(nom){
      apiFetch('logAccesIndex',{conseiller:nom,userAgent:navigator.userAgent}).catch(()=>{});
    }
  }
  function handleEdit(id){setEditingId(id);setPrefillData(null);setView('saisie');}
  // isNewEntry=true : le(s) nouvel(aux) atelier(s) est/sont déjà dans `entries`
  // via onNewEntry (insertion locale) — inutile d'attendre un aller-retour
  // GAS complet pour afficher Historique. Le prochain rechargement réel
  // (auto 5 min, ou manuel) resynchronise avec le serveur. Une modification
  // (édition) n'a pas ce raccourci : loadData() reste nécessaire.
  function handleSaved(isNewEntry){ if(!isNewEntry) loadData(); setView('historique'); }
  async function handleDelete(id){
    try{const res=await apiFetch('delete',{_id:id});if(!res.ok)throw new Error(res.error);showToast('✅ Atelier supprimé');loadData();}
    catch(err){showToast('❌ '+err.message,false);}
  }
  function handleDuplicate(entry){
    const{_id,_n,date,horaire,ampm,inscrits,presents,remarques,...rest}=entry;
    setPrefillData({...rest});setEditingId(null);setView('saisie');
  }

  const conseillerActifs = React.useMemo(()=>lists.conseillers.filter(c=>!inactifsSet.has(c)),[lists.conseillers,inactifsSet]);

  // ── Vue Accueil ───────────────────────────────────────────────
  // maintenance===null (réponse pas encore arrivée) est traité comme "pas en
  // maintenance" : on affiche la landing tout de suite, sans attendre. Seule
  // une confirmation positive de getConfig bascule sur MaintenanceScreen.
  if(maintenance && maintenance!==false) return CE(MaintenanceScreen,{msg:maintenance.msg});

  if(!authed){
    return CE(VueLoginIndex,{
      conseillers:lists.conseillers,
      onSuccess:(nom,res)=>{ window.onLoginSuccess(nom,res); setAuthed(true); handleChoixConseiller(nom); }
    });
  }

  if(view==='accueil'){
    const now=new Date();
    const moisKey=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    // Mois précédent pour la tendance
    const prevDate=new Date(now.getFullYear(),now.getMonth()-1,1);
    const prevKey=`${prevDate.getFullYear()}-${String(prevDate.getMonth()+1).padStart(2,'0')}`;
    const statsMois={};const statsPrev={};const statsRealises={};const statsAnnules={};
    entries.forEach(e=>{
      if(!e.conseiller) return;
      if(e.date&&e.date.startsWith(moisKey)){
        statsMois[e.conseiller]=(statsMois[e.conseiller]||0)+1;
        if(e.statut==='Réalisé') statsRealises[e.conseiller]=(statsRealises[e.conseiller]||0)+1;
        if(e.statut==='Annulé')  statsAnnules[e.conseiller]=(statsAnnules[e.conseiller]||0)+1;
      }
      if(e.date&&e.date.startsWith(prevKey)) statsPrev[e.conseiller]=(statsPrev[e.conseiller]||0)+1;
    });

    return CE('div',null,
      CE('nav',{style:{background:NAV_DEFAULT_COLOR}},
        CE('span',{className:'logo'},'🖥️ Ateliers Inclusion Numérique'),
        loading&&CE('span',{style:{fontSize:11,color:'rgba(255,255,255,.6)',marginLeft:8,display:'flex',alignItems:'center',gap:5}},
          CE('span',{className:'spinner',style:{borderTopColor:'rgba(255,255,255,.8)',borderColor:'rgba(255,255,255,.2)'}}),
          'Chargement…'),
        CE('button',{onClick:()=>setDarkMode(d=>!d),style:{background:'none',border:'none',cursor:'pointer',fontSize:18,padding:'2px 6px',lineHeight:1,marginLeft:'auto'},'aria-label':'Mode sombre'},darkMode?'☀️':'🌙')
      ),
      CE('div',{className:'main'},
        error
          ? CE('div',{className:'error-box'},CE('strong',null,'❌ Impossible de charger'),CE('span',null,error),CE('button',{className:'btn btn-primary',onClick:()=>loadData()},'🔄 Réessayer'))
          : CE('div',null,
              // Le seul retour visuel était un « Chargement… » de 11 px dans la
              // barre du haut : la landing semblait figée pendant les ~20 s du
              // getAll. AttenteGAS dit où on en est, avec un compteur.
              loading&&CE(AttenteGAS,null),
              !loading&&entries.length>0&&CE('div',{className:'accueil-stats'},
                lists.conseillers.map(c=>{
                  const n=statsMois[c]||0;
                  const prev=statsPrev[c]||0;
                  const diff=n-prev;
                  const trendIco=diff>0?'↑':diff<0?'↓':'→';
                  const trendColor=diff>0?'#16a34a':diff<0?'#dc2626':'#9ca3af';
                  const color=conseillerColor(c);
                  const realises=statsRealises[c]||0;
                  const annules=statsAnnules[c]||0;
                  const base=n-annules;
                  const taux=base>0?Math.round(realises/base*100):null;
                  const tauxColor=taux===null?'#9ca3af':taux>=75?'#16a34a':taux>=50?'#d97706':'#dc2626';
                  return CE('div',{key:c,className:'accueil-stat-chip',style:{background:color+'12',border:`1px solid ${color}30`,color}},
                    CE('span',{style:{fontWeight:700}},c.split(' ')[0]),
                    CE('span',{className:'accueil-stat-count',style:{background:color}},n),
                    CE('span',{style:{fontSize:10,color:'var(--text-3)'}},'ce mois'),
                    taux!==null&&CE('span',{style:{fontSize:10,fontWeight:700,color:tauxColor}},taux+'%'),
                    prev>0&&CE('span',{className:'accueil-stat-trend',style:{color:trendColor}},`${trendIco}${Math.abs(diff)}`)
                  );
                })
              ),
              CE(VueAccueilStatic,{onChoix:handleChoixConseiller,conseillers:lists.conseillers})
            )
      ),
      CE('div',{id:'toast',className:'toast',style:{opacity:0}})
    );
  }

  // ── Vue principale avec bottom nav ────────────────────────────
  const accentColor = filtreConseiller ? conseillerColor(filtreConseiller) : NAV_DEFAULT_COLOR;
  const meta = VIEW_META_F[view]||{ico:'📄',label:view,group:''};
  const dateLabel = new Date().toLocaleDateString('fr-FR',{weekday:'short',day:'numeric',month:'long'});

  // Bouton bottom nav
  const navBtn=(v,ico,lbl,visible=true)=>visible&&CE('button',{
    key:v,
    className:'bnav-btn'+(view===v?' active':''),
    onClick:()=>setView(v),
    'aria-label':lbl,
    'aria-current':view===v?'page':undefined
  },
    CE('span',{className:'bnav-ico','aria-hidden':'true'},ico),
    CE('span',{className:'bnav-lbl'},lbl)
  );

  return CE('div',{className:'app-shell-v2'},

    // ── Topbar compacte ──────────────────────────────────────
    CE('header',{className:'app-topbar-v2',style:{borderBottom:`2px solid ${accentColor}`}},
      CE('div',{className:'app-topbar-v2-left'},
        CE('span',{style:{fontSize:16},'aria-hidden':'true'},meta.ico),
        CE('span',{className:'app-topbar-v2-title'},meta.label),
        meta.group&&CE('span',{className:'app-topbar-v2-sub'},'— '+meta.group)
      ),
      CE('div',{className:'app-topbar-v2-right'},
        !online&&CE('span',{className:'offline-badge'},'📡'),
        loading&&CE('span',{className:'spinner',style:{borderTopColor:accentColor,borderColor:'var(--border)'}}),
        !loading&&lastSync&&CE('span',{className:'topbar-sync-info'},
          lastSync.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})
        ),
        CE('button',{onClick:()=>setDarkMode(d=>!d),style:{background:'none',border:'none',cursor:'pointer',fontSize:18,padding:'2px 4px',lineHeight:1},'aria-label':'Mode sombre'},darkMode?'☀️':'🌙'),
        CE('select',{className:'topbar-year-sel',value:annee,onChange:e=>setAnnee(e.target.value),title:'Année'},
          [String(new Date().getFullYear()-1),String(new Date().getFullYear()),String(new Date().getFullYear()+1)].map(y=>CE('option',{key:y,value:y},y))
        ),
        newEntries.length>0&&CE('button',{
          className:'topbar-notif-btn',
          onClick:()=>{setView('historique');document.dispatchEvent(new CustomEvent('ateliers:highlight',{detail:{ids:newEntries.map(e=>e._id)}}));setNewEntries([]);}
        },'🔔 ',CE('span',{className:'notif-badge'},newEntries.length)),
        filtreConseiller&&CE('div',{style:{position:'relative'},onMouseDown:e=>e.stopPropagation()},
          CE('button',{
            className:'app-topbar-conseiller',
            style:{background:accentColor},
            onClick:()=>setShowPicker(p=>!p)
          }, filtreConseiller, CE('span',{style:{fontSize:10,opacity:.75}},' ▾')),
          showPicker&&CE('div',{className:'conseiller-picker'},
            conseillerActifs.map(c=>CE('div',{
              key:c,
              className:'conseiller-picker-item'+(c===filtreConseiller?' active':''),
              onClick:()=>handleChoixConseiller(c)
            },
              CE('span',{className:'conseiller-picker-dot',style:{background:conseillerColor(c)}}),
              c,
              c===filtreConseiller&&CE('span',{style:{marginLeft:'auto',fontSize:11,color:'var(--text-3)'}},'✓')
            ))
          )
        ),
        !filtreConseiller&&CE('button',{
          className:'topbar-changer-btn',
          onClick:()=>{ resetConseiller(); setView('accueil'); }
        },'Changer'),
        CE('button',{
          className:'topbar-changer-btn',
          title:'Déconnexion',
          onClick:handleLogout
        },'🚪')
      )
    ),

    // ── Contenu principal ────────────────────────────────────
    CE('main',{className:'app-main-v2'},
      error&&CE('div',{className:'error-box'},CE('strong',null,'❌ Impossible de charger'),CE('span',null,error),CE('button',{className:'btn btn-primary',onClick:()=>loadData()},'🔄 Réessayer')),
      loading&&!error&&CE('div',null,
        CE(AttenteGAS,null),
        [1,2,3].map(i=>CE('div',{key:i,className:'skeleton skeleton-card'}))
      ),
      !loading&&!error&&CE('div',{className:'view-anim',key:view+'_'+(filtreConseiller||'all')},
        view==='saisie'&&visibility.saisie&&CE(VueSaisie,{entries,onSaved:handleSaved,onNewEntry:e=>{if(String(e.date||'').slice(0,4)===annee)setEntries(prev=>[e,...prev]);setNewEntries(n=>[e,...n]);setSeenIds(s=>{const ns=new Set(s);ns.add(e._id);return ns;});},lists,editingId,onClearEdit:()=>setEditingId(null),prefillData,onClearPrefill:()=>setPrefillData(null),accentColor:conseillerColor(filtreConseiller||'')}),
        view==='historique'&&visibility.historique&&CE(VueHistorique,{entries,onEdit:handleEdit,onDelete:handleDelete,onRefresh:()=>loadData(),onDuplicate:handleDuplicate,initConseiller:filtreConseiller,onResetConseiller:()=>{},canDelete:true,onChangeConseiller:c=>setFiltreConseiller(c==='Tous'?null:c)}),
        view==='agenda'&&visibility.agenda&&CE(VueAgendaSemaine,{entries,onEdit:handleEdit,onDelete:handleDelete,onDuplicate:handleDuplicate,canDelete:true,initConseiller:filtreConseiller,accentColor}),
        view==='calendrier'&&visibility.calendrier&&CE(VueCalendrier,{entries,onEdit:handleEdit,onDelete:handleDelete,onRefresh:()=>loadData(),onDuplicate:handleDuplicate,initConseiller:filtreConseiller,onResetConseiller:()=>{},canDelete:true,onChangeConseiller:c=>setFiltreConseiller(c==='Tous'?null:c)}),
        view==='dashboard'&&visibility.dashboard&&CE(VueDashboardTabs,{entries,conseillers:lists.conseillers}),
        view==='carte'&&visibility.carte&&CE(VueCarte,{entries,active:view==='carte'}),
        view==='roadmap'&&visibility.roadmap&&CE(VueRoadmap,{entries,annee,conseillers:lists.conseillers}),
        view==='bingo'&&visibility.bingo&&CE(VueBingo,{entries})
      )
    ),

    // ── Bottom nav scrollable ───────────────────────────────
    CE('nav',{className:'bottom-nav-v2','aria-label':'Navigation principale'},
      CE('div',{className:'bottom-nav-scroll'},
        navBtn('saisie',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('path',{d:'M12 5v14M5 12h14'})),
          'Nouveau', visibility.saisie),
        navBtn('historique',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('path',{d:'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'})),
          'Historique', visibility.historique),
        navBtn('agenda',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('rect',{x:3,y:4,width:18,height:18,rx:2}),CE('path',{d:'M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01'})),
          'Agenda', visibility.agenda),
        navBtn('calendrier',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('rect',{x:3,y:4,width:18,height:18,rx:2}),CE('path',{d:'M16 2v4M8 2v4M3 10h18'})),
          'Calendrier', visibility.calendrier),
        navBtn('carte',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('polygon',{points:'1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6'}),CE('line',{x1:8,y1:2,x2:8,y2:18}),CE('line',{x1:16,y1:6,x2:16,y2:22})),
          'Carte', visibility.carte),
        navBtn('roadmap',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('path',{d:'M3 17l4-8 4 4 4-6 4 4'})),
          'Roadmap', visibility.roadmap),
        navBtn('dashboard',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('path',{d:'M3 3v18h18'}),CE('rect',{x:7,y:10,width:3,height:8,rx:1}),CE('rect',{x:13,y:6,width:3,height:12,rx:1})),
          'Stats', visibility.dashboard),
        navBtn('bingo',
          CE('svg',{width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:2,strokeLinecap:'round',strokeLinejoin:'round'},CE('circle',{cx:12,cy:12,r:10}),CE('circle',{cx:12,cy:12,r:6}),CE('circle',{cx:12,cy:12,r:2})),
          'Bingo', visibility.bingo)
      )
    ),

    CE('div',{id:'toast',className:'toast',style:{opacity:0}})
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(CE(App));
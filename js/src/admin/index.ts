import app from 'flarum/admin/app';

function ordinal(n){let s=["th","st","nd","rd"];let v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}
function saveSetting(key,val){return app.request({method:"POST",url:app.forum.attribute("apiUrl")+"/settings",body:{[key]:val}}).then(function(){app.data.settings[key]=val;});}
function getSettingVal(key,fallback){let v=app.data.settings[key];return(v===undefined||v===null)?fallback:v;}

let TIMEZONES=[
  {tz:"Pacific/Honolulu",    label:"Hawaii"},
  {tz:"America/Anchorage",   label:"Alaska"},
  {tz:"America/Los_Angeles", label:"Pacific Time (US & Canada)"},
  {tz:"America/Denver",      label:"Mountain Time (US & Canada)"},
  {tz:"America/Chicago",     label:"Central Time (US & Canada)"},
  {tz:"America/New_York",    label:"Eastern Time (US & Canada)"},
  {tz:"America/Halifax",     label:"Atlantic Time (Canada)"},
  {tz:"America/Sao_Paulo",   label:"Brasilia"},
  {tz:"Atlantic/Azores",     label:"Azores"},
  {tz:"Europe/London",       label:"London"},
  {tz:"Europe/Paris",        label:"Paris / Berlin / Rome"},
  {tz:"Europe/Helsinki",     label:"Helsinki / Kyiv"},
  {tz:"Europe/Moscow",       label:"Moscow"},
  {tz:"Asia/Dubai",          label:"Dubai"},
  {tz:"Asia/Karachi",        label:"Karachi"},
  {tz:"Asia/Kolkata",        label:"Mumbai / New Delhi"},
  {tz:"Asia/Bangkok",        label:"Bangkok / Jakarta"},
  {tz:"Asia/Shanghai",       label:"Beijing / Singapore"},
  {tz:"Asia/Tokyo",          label:"Tokyo / Seoul"},
  {tz:"Australia/Sydney",    label:"Sydney"},
  {tz:"Pacific/Auckland",    label:"Auckland"},
];
function tzOffsetLabel(tz){try{let fmt=new Intl.DateTimeFormat("en-US",{timeZone:tz,timeZoneName:"shortOffset"});let parts=fmt.formatToParts(new Date());let p=parts.find(function(x){return x.type==="timeZoneName";});return p?p.value.replace("GMT","UTC"):"UTC";}catch(e){return "UTC";}}
function buildHourOptions(tz){let opts={};let label=tzOffsetLabel(tz);for(let h=0;h<24;h++){let padded=h<10?"0"+h:""+h;let lbl=padded+":00 "+label;if(h===0)lbl+=" (midnight)";if(h===12)lbl+=" (noon)";opts[String(h)]=lbl;}return opts;}
let weekDayOptions={"0":"Sunday","1":"Monday","2":"Tuesday","3":"Wednesday","4":"Thursday","5":"Friday","6":"Saturday"};
let monthDayOptions={};for(let d=1;d<=28;d++){monthDayOptions[String(d)]=ordinal(d);}

let FIXED_SECTIONS=[
  {key:"discussions",labelKey:"ernestdefoe-digest-mail.admin.integrations.section_discussions",label:"Discussions",    icon:"fas fa-comments",  iconBg:"#3b82f6",iconColor:"#fff"},
  {key:"members",    labelKey:"ernestdefoe-digest-mail.admin.integrations.section_members",label:"New Members",    icon:"fas fa-user-plus", iconBg:"#10b981",iconColor:"#fff"},
  {key:"stats",      labelKey:"ernestdefoe-digest-mail.admin.integrations.section_stats",label:"Community Stats",icon:"fas fa-chart-bar", iconBg:"#6366f1",iconColor:"#fff"},
];
let INTEGRATION_SECTIONS={
  leaderboard:{key:"leaderboard",labelKey:"ernestdefoe-digest-mail.admin.integrations.section_leaderboard",label:"Leaderboard",icon:"fas fa-trophy",      iconBg:"#3498db",iconColor:"#fff"},
  badges:     {key:"badges",     labelKey:"ernestdefoe-digest-mail.admin.integrations.section_badges",label:"Badges",     icon:"fas fa-award",       iconBg:"#8b5cf6",iconColor:"#fff"},
  pickem:     {key:"pickem",     labelKey:"ernestdefoe-digest-mail.admin.integrations.section_pickem",label:"Pick'em",    icon:"fas fa-football-ball",iconBg:"#16a34a",iconColor:"#fff"},
  picks:      {key:"picks",      labelKey:"ernestdefoe-digest-mail.admin.integrations.section_picks",label:"CFB Picks",   icon:"fas fa-football",     iconBg:"#69c6b9",iconColor:"#1A2744"},
  gamepedia:          {key:"gamepedia",         labelKey:"ernestdefoe-digest-mail.admin.integrations.section_gamepedia",label:"Gamepedia",          icon:"fas fa-gamepad",     iconBg:"#e85d04",iconColor:"#fff"},
  resofireGamepedia:  {key:"resofireGamepedia", labelKey:"ernestdefoe-digest-mail.admin.integrations.section_resofire_gamepedia",label:"Resofire Gamepedia", icon:"fas fa-gamepad",     iconBg:"#1a1a2e",iconColor:"#e94560"},
  favorites:          {key:"favorites",         labelKey:"ernestdefoe-digest-mail.admin.integrations.section_favorites",label:"Favorites",          icon:"fas fa-heart",       iconBg:"#e11d48",iconColor:"#fff"},
  awards:     {key:"awards",     labelKey:"ernestdefoe-digest-mail.admin.integrations.section_awards",label:"Awards",     icon:"fas fa-star",        iconBg:"#f59e0b",iconColor:"#fff"},
};
let DEFAULT_ORDER=["discussions","members","stats","leaderboard","badges","pickem","picks","gamepedia","resofireGamepedia","favorites","awards"];

let ExtIcon={view:function(vnode){let a=vnode.attrs;let sz=a.size||40;let fz=Math.round(sz*0.44);return m("div",{style:"width:"+sz+"px;height:"+sz+"px;border-radius:8px;background-color:"+(a.iconBg||"#6b7280")+";display:flex;align-items:center;justify-content:center;flex-shrink:0;"},m("i",{className:a.iconName||"fas fa-puzzle-piece",style:"color:"+(a.iconColor||"#fff")+";font-size:"+fz+"px;"}));}};

let IntegrationToggle={
  oninit:function(vnode){let key=vnode.attrs.settingKey;let ext=vnode.attrs.extData||{};let saved=app.data.settings[key];if(saved===undefined||saved===null){saved=ext.enabled?"1":"0";}vnode.state.on=saved==="1"||saved===true||saved===1;vnode.state.saving=false;},
  toggle:function(vnode){let ext=vnode.attrs.extData||{};if(!ext.enabled||vnode.state.saving)return;vnode.state.on=!vnode.state.on;vnode.state.saving=true;let newVal=vnode.state.on?"1":"0";saveSetting(vnode.attrs.settingKey,newVal).then(function(){vnode.state.saving=false;m.redraw();}).catch(function(){vnode.state.on=!vnode.state.on;vnode.state.saving=false;m.redraw();});},
  view:function(vnode){let a=vnode.attrs;let s=vnode.state;let tr=function(k,v){return app.translator.trans(k,v);};let ext=a.extData||{};let installed=!!ext.enabled;let on=installed&&s.on;let trackBg=!installed?"var(--control-bg)":on?"var(--primary-color,#4f46e5)":"var(--control-color,#d1d5db)";let thumbLeft=on?"22px":"2px";let cardOpacity=installed?"1":"0.55";let cursor=installed?"pointer":"not-allowed";let statusText=installed?(a.installedNote||tr("ernestdefoe-digest-mail.admin.integrations.extension_active")):(a.notInstalledNote||tr("ernestdefoe-digest-mail.admin.integrations.not_installed_or_disabled"));let statusColor=installed?"#16a34a":"var(--muted-color)";return m("div",{style:"display:flex;align-items:center;gap:16px;padding:16px 20px;border-radius:8px;background:var(--control-bg);border:1px solid var(--control-bg);opacity:"+cardOpacity+";margin-bottom:10px;transition:opacity .2s;"},m(ExtIcon,{iconName:ext.iconName,iconColor:ext.iconColor,iconBg:ext.iconBg,size:44}),m("div",{style:"flex:1;min-width:0;"},m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:3px;"},m("span",{style:"font-size:16px;font-weight:700;color:var(--heading-color,var(--text-color));"},ext.title||a.settingKey),m("span",{style:"font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;background:"+(installed?"rgba(34,197,94,.15)":"var(--control-bg)")+";color:"+(installed?"#16a34a":"var(--muted-color)")+";"},installed?tr("ernestdefoe-digest-mail.admin.integrations.status_active"):tr("ernestdefoe-digest-mail.admin.integrations.status_inactive"))),m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.45;margin-bottom:4px;"},a.description),m("div",{style:"font-size:12px;color:"+statusColor+";"},statusText)),m("div",{style:"flex-shrink:0;cursor:"+cursor+";user-select:none;",title:installed?(on?tr("ernestdefoe-digest-mail.admin.integrations.toggle_disable_title"):tr("ernestdefoe-digest-mail.admin.integrations.toggle_enable_title")):tr("ernestdefoe-digest-mail.admin.integrations.toggle_install_first_title"),onclick:function(){IntegrationToggle.toggle(vnode);}},m("div",{style:"position:relative;width:46px;height:26px;border-radius:13px;background-color:"+trackBg+";transition:background-color .2s;"},m("div",{style:"position:absolute;top:3px;left:"+thumbLeft+";width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .15s;"}))));} 
};

let FrequencyToggle={
  oninit:function(vnode){let saved=app.data.settings[vnode.attrs.settingKey];if(saved===undefined||saved===null)saved=vnode.attrs.defaultOn?"1":"0";vnode.state.on=saved==="1"||saved===true||saved===1;vnode.state.saving=false;},
  toggle:function(vnode){if(vnode.state.saving)return;vnode.state.on=!vnode.state.on;vnode.state.saving=true;let newVal=vnode.state.on?"1":"0";saveSetting(vnode.attrs.settingKey,newVal).then(function(){vnode.state.saving=false;m.redraw();}).catch(function(){vnode.state.on=!vnode.state.on;vnode.state.saving=false;m.redraw();});},
  view:function(vnode){let a=vnode.attrs;let s=vnode.state;let tr=function(k){return app.translator.trans(k);};let on=s.on;let trackBg=on?"var(--primary-color,#4f46e5)":"var(--control-color,#d1d5db)";let thumbLeft=on?"22px":"2px";return m("div",{style:"display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-radius:8px;background:var(--control-bg);border:1px solid var(--control-bg);margin-bottom:10px;"},m("div",{style:"display:flex;align-items:center;gap:14px;flex:1;"},m("div",{style:"width:44px;height:44px;border-radius:8px;flex-shrink:0;background:"+a.iconBg+";display:flex;align-items:center;justify-content:center;font-size:20px;"},a.emoji),m("div",null,m("div",{style:"font-size:15px;font-weight:700;color:var(--heading-color,var(--text-color));margin-bottom:2px;"},a.label),m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.4;"},a.description))),m("div",{style:"flex-shrink:0;cursor:pointer;margin-left:20px;",title:on?tr("ernestdefoe-digest-mail.admin.frequency.toggle_disable_title"):tr("ernestdefoe-digest-mail.admin.frequency.toggle_enable_title"),onclick:function(){FrequencyToggle.toggle(vnode);}},m("div",{style:"position:relative;width:46px;height:26px;border-radius:13px;background-color:"+trackBg+";transition:background-color .2s;"},m("div",{style:"position:absolute;top:3px;left:"+thumbLeft+";width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .15s;"}))));}
};

let NumberSetting={
  oninit:function(vnode){vnode.state.value=getSettingVal(vnode.attrs.settingKey,"");vnode.state.saving=false;vnode.state.saved=false;},
  save:function(vnode){if(vnode.state.saving)return;vnode.state.saving=true;saveSetting(vnode.attrs.settingKey,String(vnode.state.value)).then(function(){vnode.state.saving=false;vnode.state.saved=true;setTimeout(function(){vnode.state.saved=false;m.redraw();},5000);m.redraw();}).catch(function(){vnode.state.saving=false;m.redraw();});},
  view:function(vnode){let a=vnode.attrs;let s=vnode.state;let tr=function(k){return app.translator.trans(k);};return m("div",{className:"Form-group",style:"margin-bottom:20px;"},m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},a.label),a.help?m("p",{className:"helpText",style:"margin-bottom:6px;"},a.help):null,m("div",{style:"display:flex;align-items:center;gap:8px;"},m("input",{className:"FormControl",type:"number",min:a.min,max:a.max,step:a.step||1,value:s.value,style:"width:100px;",oninput:function(e){s.value=e.target.value;s.saved=false;},onblur:function(){NumberSetting.save(vnode);}}),s.saved?m("span",{style:"font-size:12px;color:#16a34a;"},"\u2713 "+tr("ernestdefoe-digest-mail.admin.settings.saved")):null));}
};

let ScheduleSection={
  oninit:function(vnode){
    let savedTz=getSettingVal("ernestdefoe-digest-mail.timezone","America/Chicago");
    let savedStart=getSettingVal("ernestdefoe-digest-mail.send_window_start",
                   getSettingVal("ernestdefoe-digest-mail.send_hour","8"));
    let savedEnd=getSettingVal("ernestdefoe-digest-mail.send_window_end",savedStart);
    vnode.state.tz=savedTz;
    vnode.state.windowStart=String(savedStart);
    vnode.state.windowEnd=String(savedEnd);
    vnode.state.saving=false;
  },
  saveStart:function(vnode,val){
    vnode.state.windowStart=val;
    saveSetting("ernestdefoe-digest-mail.send_window_start",val);
    // Keep legacy send_hour in sync so existing code paths still work
    saveSetting("ernestdefoe-digest-mail.send_hour",val);
    // If end < start, snap end to start (single-hour mode)
    if(parseInt(vnode.state.windowEnd,10)<parseInt(val,10)){
      vnode.state.windowEnd=val;
      saveSetting("ernestdefoe-digest-mail.send_window_end",val);
    }
    m.redraw();
  },
  saveEnd:function(vnode,val){
    vnode.state.windowEnd=val;
    saveSetting("ernestdefoe-digest-mail.send_window_end",val);
    m.redraw();
  },
  saveTz:function(vnode,tz){
    vnode.state.tz=tz;
    saveSetting("ernestdefoe-digest-mail.timezone",tz);
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k,v){return app.translator.trans(k,v);};
    let hourOpts=buildHourOptions(s.tz);
    let tzOpts=TIMEZONES.map(function(z){
      return m("option",{value:z.tz,selected:s.tz===z.tz},tzOffsetLabel(z.tz)+" — "+z.label);
    });
    let isWindow=parseInt(s.windowEnd,10)>parseInt(s.windowStart,10);
    let windowSummary=isWindow
      ?tr("ernestdefoe-digest-mail.admin.schedule.window_spread",{from:hourOpts[s.windowStart],to:hourOpts[s.windowEnd]})
      :tr("ernestdefoe-digest-mail.admin.schedule.window_single",{from:hourOpts[s.windowStart]});
    return m("div",null,
      // Timezone
      m("div",{className:"Form-group",style:"margin-bottom:16px;"},
        m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},tr("ernestdefoe-digest-mail.admin.settings.timezone_label")),
        m("p",{className:"helpText",style:"margin-bottom:6px;"},tr("ernestdefoe-digest-mail.admin.settings.timezone_help")),
        m("select",{className:"FormControl Select-input",value:s.tz,style:"max-width:360px;padding-bottom:8px;height:auto;line-height:1.4;",
          onchange:function(e){ScheduleSection.saveTz(vnode,e.target.value);}
        },tzOpts)
      ),
      // Window start
      m("div",{className:"Form-group",style:"margin-bottom:12px;"},
        m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},tr("ernestdefoe-digest-mail.admin.settings.send_window_start_label")),
        m("p",{className:"helpText",style:"margin-bottom:6px;"},tr("ernestdefoe-digest-mail.admin.settings.send_window_start_help")),
        m("select",{className:"FormControl Select-input",value:s.windowStart,style:"max-width:260px;padding-bottom:8px;height:auto;line-height:1.4;",
          onchange:function(e){ScheduleSection.saveStart(vnode,e.target.value);}
        },Object.keys(hourOpts).map(function(k){return m("option",{value:k,selected:s.windowStart===k},hourOpts[k]);}))
      ),
      // Window end
      m("div",{className:"Form-group",style:"margin-bottom:16px;"},
        m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},tr("ernestdefoe-digest-mail.admin.settings.send_window_end_label")),
        m("p",{className:"helpText",style:"margin-bottom:6px;"},tr("ernestdefoe-digest-mail.admin.settings.send_window_end_help")),
        m("select",{className:"FormControl Select-input",value:s.windowEnd,style:"max-width:260px;padding-bottom:8px;height:auto;line-height:1.4;",
          onchange:function(e){ScheduleSection.saveEnd(vnode,e.target.value);}
        },Object.keys(hourOpts).map(function(k){return m("option",{value:k,selected:s.windowEnd===k},hourOpts[k]);}))
      ),
      // Summary notice
      m("div",{style:"padding:10px 14px;border-radius:8px;background:var(--control-bg);border-left:3px solid "+(isWindow?"#10b981":"var(--primary-color,#4f46e5)")+";margin-bottom:4px;"},
        m("p",{style:"margin:0;font-size:13px;color:var(--muted-color);line-height:1.5;"},
          m("strong",{style:"color:var(--heading-color,var(--text-color));"},isWindow?tr("ernestdefoe-digest-mail.admin.schedule.spread_send_label"):tr("ernestdefoe-digest-mail.admin.schedule.single_send_label")),
          windowSummary
        )
      )
    );
  }
};

let SelectSetting={
  oninit:function(vnode){vnode.state.value=getSettingVal(vnode.attrs.settingKey,"");vnode.state.saving=false;},
  view:function(vnode){let a=vnode.attrs;let s=vnode.state;return m("div",{className:"Form-group",style:"margin-bottom:20px;"},m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},a.label),a.help?m("p",{className:"helpText",style:"margin-bottom:6px;"},a.help):null,m("select",{className:"FormControl Select-input",value:s.value,style:"max-width:260px;padding-bottom:8px;height:auto;line-height:1.4;",onchange:function(e){s.value=e.target.value;saveSetting(a.settingKey,e.target.value);}},Object.keys(a.options).map(function(k){return m("option",{value:k,selected:s.value===k},a.options[k]);})));}
};

let OnboardingSection={
  oninit:function(vnode){
    vnode.state.mode=getSettingVal("ernestdefoe-digest-mail.onboarding_mode","none");
    vnode.state.frequency=getSettingVal("ernestdefoe-digest-mail.onboarding_frequency","weekly");
  },
  saveMode:function(vnode,val){
    vnode.state.mode=val;
    saveSetting("ernestdefoe-digest-mail.onboarding_mode",val);
    m.redraw();
  },
  saveFrequency:function(vnode,val){
    vnode.state.frequency=val;
    saveSetting("ernestdefoe-digest-mail.onboarding_frequency",val);
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k){return app.translator.trans(k);};
    let modeOptions={
      "none":        tr("ernestdefoe-digest-mail.admin.onboarding.mode_none"),
      "auto_enroll": tr("ernestdefoe-digest-mail.admin.onboarding.mode_auto_enroll"),
      "opt_in_modal":tr("ernestdefoe-digest-mail.admin.onboarding.mode_opt_in_modal"),
    };
    let freqOpts={};
    let freqLabels={"daily":tr("ernestdefoe-digest-mail.admin.frequency.daily"),"weekly":tr("ernestdefoe-digest-mail.admin.frequency.weekly"),"monthly":tr("ernestdefoe-digest-mail.admin.frequency.monthly")};
    ["daily","weekly","monthly"].forEach(function(f){
      let enabled=app.data.settings["ernestdefoe-digest-mail.allow_"+f];
      if(enabled==="1"||enabled===true||enabled===1)freqOpts[f]=freqLabels[f];
    });
    if(s.mode==="auto_enroll"&&freqOpts[s.frequency]===undefined){
      let firstAvailable=Object.keys(freqOpts)[0];
      if(firstAvailable&&firstAvailable!==s.frequency){
        s.frequency=firstAvailable;
        saveSetting("ernestdefoe-digest-mail.onboarding_frequency",firstAvailable);
      }
    }
    return m("div",null,
      m("div",{className:"Form-group",style:"margin-bottom:16px;"},
        m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},
          tr("ernestdefoe-digest-mail.admin.onboarding.mode_label")
        ),
        m("p",{className:"helpText",style:"margin-bottom:6px;"},
          tr("ernestdefoe-digest-mail.admin.onboarding.mode_help")
        ),
        m("select",{
          className:"FormControl Select-input",
          value:s.mode,
          style:"max-width:360px;padding-bottom:8px;height:auto;line-height:1.4;",
          onchange:function(e){OnboardingSection.saveMode(vnode,e.target.value);}
        },Object.keys(modeOptions).map(function(k){
          return m("option",{value:k,selected:s.mode===k},modeOptions[k]);
        }))
      ),
      s.mode==="auto_enroll"?m("div",{className:"Form-group",style:"margin-bottom:4px;"},
        m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},
          tr("ernestdefoe-digest-mail.admin.onboarding.frequency_label")
        ),
        m("p",{className:"helpText",style:"margin-bottom:6px;"},
          tr("ernestdefoe-digest-mail.admin.onboarding.frequency_help")
        ),
        Object.keys(freqOpts).length===0
          ?m("p",{style:"font-size:13px;color:#dc2626;"},tr("ernestdefoe-digest-mail.admin.onboarding.no_frequencies_enabled"))
          :m("select",{
              className:"FormControl Select-input",
              value:s.frequency,
              style:"max-width:260px;padding-bottom:8px;height:auto;line-height:1.4;",
              onchange:function(e){OnboardingSection.saveFrequency(vnode,e.target.value);}
            },Object.keys(freqOpts).map(function(k){
              return m("option",{value:k,selected:s.frequency===k},freqOpts[k]);
            }))
      ):null
    );
  }
};

let TokenCheckerSection={
  oninit:function(vnode){
    vnode.state.token="";
    vnode.state.result=null;
    vnode.state.error=null;
    vnode.state.loading=false;
  },
  check:function(state){
    let token=state.token.trim();
    if(!token){state.error=app.translator.trans("ernestdefoe-digest-mail.admin.token_checker.error_empty");state.result=null;m.redraw();return;}
    state.loading=true;state.result=null;state.error=null;m.redraw();
    app.request({
      method:"GET",
      url:app.forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/check-token?token="+encodeURIComponent(token)
    }).then(function(d){
      state.loading=false;state.result=d;m.redraw();
    }).catch(function(e){
      state.loading=false;
      state.error=(e&&e.response&&e.response.errors&&e.response.errors[0]&&e.response.errors[0].detail)||(e&&e.message)||app.translator.trans("ernestdefoe-digest-mail.admin.token_checker.error_generic");
      m.redraw();
    });
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k){return app.translator.trans(k);};
    let formatDate=function(str){
      if(!str)return "—";
      let d=new Date(str.replace(" ","T")+"Z");
      return d.toLocaleDateString(undefined,{month:"short",day:"numeric",year:"numeric"})+" at "+d.toLocaleTimeString(undefined,{hour:"2-digit",minute:"2-digit"});
    };
    return m("div",{className:"ExtensionPage-settings"},
      m("div",{style:"max-width:600px;margin:0 auto;"},
        m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},tr("ernestdefoe-digest-mail.admin.token_checker.heading")),
        m("p",{className:"helpText",style:"margin-bottom:12px;"},tr("ernestdefoe-digest-mail.admin.token_checker.help")),
        m("div",{className:"Form-group"},
          m("div",{style:"display:flex;gap:8px;align-items:center;"},
            m("input",{
              className:"FormControl",
              type:"text",
              placeholder:tr("ernestdefoe-digest-mail.admin.token_checker.placeholder"),
              value:s.token,
              style:"flex:1;font-family:monospace;font-size:12px;",
              oninput:function(e){s.token=e.target.value;s.result=null;s.error=null;},
              onkeydown:function(e){if(e.key==="Enter"){e.preventDefault();TokenCheckerSection.check(s);}}
            }),
            m("button",{
              className:"Button Button--primary",
              disabled:s.loading,
              onclick:function(e){e.preventDefault();TokenCheckerSection.check(s);}
            },s.loading?tr("ernestdefoe-digest-mail.admin.token_checker.checking_button"):tr("ernestdefoe-digest-mail.admin.token_checker.check_button"))
          )
        ),
        s.result?m("div",{className:"Alert Alert--success",style:"margin-top:12px;"},
          m("div",{style:"font-weight:700;margin-bottom:4px;"},"\u2713 "+tr("ernestdefoe-digest-mail.admin.token_checker.valid_token")),
          m("div",{style:"font-size:13px;"},
            m("span",{style:"color:var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.token_checker.user_label")),
            m("strong",null,s.result.username),
            m("span",{style:"color:var(--muted-color);margin-left:16px;"},tr("ernestdefoe-digest-mail.admin.token_checker.created_label")),
            m("strong",null,formatDate(s.result.created_at)),
            m("span",{style:"color:var(--muted-color);margin-left:16px;"},tr("ernestdefoe-digest-mail.admin.token_checker.expires_label")),
            m("strong",null,formatDate(s.result.expires_at))
          )
        ):null,
        s.error?m("div",{className:"Alert Alert--error",style:"margin-top:12px;"},s.error):null
      )
    );
  }
};

let TestSendSection={
  oninit:function(vnode){vnode.state.email="";vnode.state.frequency="weekly";vnode.state.theme="light";vnode.state.loading=false;vnode.state.result=null;vnode.state.error=null;},
  send:function(state){let email=state.email.trim();if(!email){state.error=app.translator.trans("ernestdefoe-digest-mail.admin.test_send.error_empty_email");state.result=null;m.redraw();return;}state.loading=true;state.result=null;state.error=null;m.redraw();app.request({method:"POST",url:app.forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/test-send",body:{email:email,frequency:state.frequency,theme:state.theme}}).then(function(data){state.loading=false;state.result=data;m.redraw();}).catch(function(e){state.loading=false;let serverMsg=(e&&e.response&&e.response.error)||(e&&e.message)||null;state.error=serverMsg||app.translator.trans("ernestdefoe-digest-mail.admin.test_send.error_generic");m.redraw();});},
  view:function(vnode){
    let state=vnode.state;let themePickerEnabled=true;let tr=function(k,v){return app.translator.trans(k,v);};
    let themeToggle=themePickerEnabled
      ?m("div",{style:"display:flex;align-items:center;gap:10px;margin-bottom:12px;"},m("label",{style:"font-size:13px;color:var(--muted-color);white-space:nowrap;"},tr("ernestdefoe-digest-mail.admin.test_send.theme_label")+":"),m("div",{style:"display:flex;gap:0;border:1px solid var(--control-bg);border-radius:6px;overflow:hidden;"},m("button",{style:"padding:6px 14px;font-size:13px;font-weight:500;border:none;cursor:pointer;"+(state.theme==="light"?"background:var(--body-bg,#fff);color:var(--text-color,#111827);box-shadow:inset 0 0 0 1px var(--control-bg);":"background:var(--control-bg);color:var(--muted-color);"),onclick:function(e){e.preventDefault();state.theme="light";m.redraw();}},m("span",{style:"margin-right:5px;"},"☀️"),tr("ernestdefoe-digest-mail.admin.test_send.theme_light")),m("button",{style:"padding:6px 14px;font-size:13px;font-weight:500;border:none;cursor:pointer;border-left:1px solid var(--control-bg);"+(state.theme==="dark"?"background:var(--header-bg,#1f2937);color:var(--header-color,#e5e7eb);":"background:var(--control-bg);color:var(--muted-color);"),onclick:function(e){e.preventDefault();state.theme="dark";m.redraw();}},m("span",{style:"margin-right:5px;"},"🌙"),tr("ernestdefoe-digest-mail.admin.test_send.theme_dark"))),m("span",{style:"font-size:12px;color:var(--muted-color);"},state.theme==="light"?tr("ernestdefoe-digest-mail.admin.test_send.theme_hint_light"):tr("ernestdefoe-digest-mail.admin.test_send.theme_hint_dark")))
      :m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:8px 12px;background:var(--control-bg);border-radius:6px;"},m("span",{style:"font-size:13px;color:var(--muted-color);"},"☀️ "+tr("ernestdefoe-digest-mail.admin.test_send.theme_light_only")));
    return m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},tr("ernestdefoe-digest-mail.admin.test_send.heading")),m("p",{className:"helpText"},tr("ernestdefoe-digest-mail.admin.test_send.help")),m("div",{className:"Form-group",style:"margin-top:1rem;"},m("input",{className:"FormControl",type:"email",placeholder:tr("ernestdefoe-digest-mail.admin.test_send.email_placeholder"),value:state.email,disabled:state.loading,oninput:function(e){state.email=e.target.value;state.result=null;state.error=null;},style:"margin-bottom:8px;"}),m("div",{style:"display:flex;align-items:center;gap:10px;margin-bottom:12px;"},m("label",{style:"font-size:13px;color:var(--muted-color);white-space:nowrap;"},tr("ernestdefoe-digest-mail.admin.test_send.frequency_label")+":"),m("select",{className:"FormControl",value:state.frequency,disabled:state.loading,style:"padding-top:6px;padding-bottom:8px;height:auto;line-height:1.5;",onchange:function(e){state.frequency=e.target.value;}},m("option",{value:"daily"},tr("ernestdefoe-digest-mail.admin.frequency.daily")),m("option",{value:"weekly"},tr("ernestdefoe-digest-mail.admin.frequency.weekly")),m("option",{value:"monthly"},tr("ernestdefoe-digest-mail.admin.frequency.monthly")))),themeToggle,m("button",{className:"Button Button--primary",disabled:state.loading,onclick:function(e){e.preventDefault();TestSendSection.send(state);}},state.loading?tr("ernestdefoe-digest-mail.admin.test_send.sending_button"):tr("ernestdefoe-digest-mail.admin.test_send.send_button"))),state.result?m("div",{className:"Alert Alert--success",style:"margin-top:1rem;"},tr("ernestdefoe-digest-mail.admin.test_send.success",{email:state.result.to,frequency:state.result.frequency})):null,state.error?m("div",{className:"Alert Alert--error",style:"margin-top:1rem;"},state.error):null));
  }
};

let SettingsTab={
  view:function(){
    let exts=(app.forum.attribute("digestExtensions"))||{};
    let tr=function(k){return app.translator.trans(k);};
    let sh=function(t){return m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},t);};
    return m("div",null,
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.content_limits")),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.featured_discussion_id",min:1,label:tr("ernestdefoe-digest-mail.admin.settings.featured_discussion_label"),help:tr("ernestdefoe-digest-mail.admin.settings.featured_discussion_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_new",         min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_new_label"),         help:tr("ernestdefoe-digest-mail.admin.settings.limit_new_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_hot",         min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_hot_label"),         help:tr("ernestdefoe-digest-mail.admin.settings.limit_hot_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_unread",      min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_unread_label"),      help:tr("ernestdefoe-digest-mail.admin.settings.limit_unread_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_members",     min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_members_label"),     help:tr("ernestdefoe-digest-mail.admin.settings.limit_members_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_leaderboard", min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_leaderboard_label"), help:tr("ernestdefoe-digest-mail.admin.settings.limit_leaderboard_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_badges",      min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_badges_label"),      help:tr("ernestdefoe-digest-mail.admin.settings.limit_badges_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_pickem",      min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_pickem_label"),      help:tr("ernestdefoe-digest-mail.admin.settings.limit_pickem_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_picks",       min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_picks_label"),       help:tr("ernestdefoe-digest-mail.admin.settings.limit_picks_help")}),
        m(SelectSetting, {settingKey:"ernestdefoe-digest-mail.picks_leaderboard_scope",options:{"alltime":tr("ernestdefoe-digest-mail.admin.settings.picks_leaderboard_scope_alltime"),"season":tr("ernestdefoe-digest-mail.admin.settings.picks_leaderboard_scope_season"),"week":tr("ernestdefoe-digest-mail.admin.settings.picks_leaderboard_scope_week")},label:tr("ernestdefoe-digest-mail.admin.settings.picks_leaderboard_scope_label"),help:tr("ernestdefoe-digest-mail.admin.settings.picks_leaderboard_scope_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_gamepedia",   min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_gamepedia_label"),   help:tr("ernestdefoe-digest-mail.admin.settings.limit_gamepedia_help")}),
        !!(exts.resofireGamepedia||{}).enabled?m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_resofire_gamepedia",min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_resofire_gamepedia_label"),help:tr("ernestdefoe-digest-mail.admin.settings.limit_resofire_gamepedia_help")}):null,
        (!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled)?m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_favorites",min:0,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_favorites_label"),help:tr("ernestdefoe-digest-mail.admin.settings.limit_favorites_help")}):null,
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.hot_reply_weight",  min:0,max:10,step:0.1,label:tr("ernestdefoe-digest-mail.admin.settings.hot_reply_weight_label"),  help:tr("ernestdefoe-digest-mail.admin.settings.hot_reply_weight_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.hot_recency_weight",min:0,max:10,step:0.1,label:tr("ernestdefoe-digest-mail.admin.settings.hot_recency_weight_label"),help:tr("ernestdefoe-digest-mail.admin.settings.hot_recency_weight_help")})
      )),
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.schedule")),
        m(ScheduleSection),
        m(SelectSetting,{settingKey:"ernestdefoe-digest-mail.weekly_day",  options:weekDayOptions,  label:tr("ernestdefoe-digest-mail.admin.settings.weekly_day_label"),  help:tr("ernestdefoe-digest-mail.admin.settings.weekly_day_help")}),
        m(SelectSetting,{settingKey:"ernestdefoe-digest-mail.monthly_day", options:monthDayOptions, label:tr("ernestdefoe-digest-mail.admin.settings.monthly_day_label"), help:tr("ernestdefoe-digest-mail.admin.settings.monthly_day_help")})
      )),

      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.user_frequency_options")),
        m("p",{className:"helpText",style:"margin-bottom:12px;"},tr("ernestdefoe-digest-mail.admin.frequency.help")),
        m(FrequencyToggle,{settingKey:"ernestdefoe-digest-mail.allow_daily",  defaultOn:false,emoji:"📅",iconBg:"#fef3c7",label:tr("ernestdefoe-digest-mail.admin.frequency.daily"),  description:tr("ernestdefoe-digest-mail.admin.frequency.daily_desc")}),
        m(FrequencyToggle,{settingKey:"ernestdefoe-digest-mail.allow_weekly", defaultOn:true, emoji:"📆",iconBg:"#ede9fe",label:tr("ernestdefoe-digest-mail.admin.frequency.weekly"), description:tr("ernestdefoe-digest-mail.admin.frequency.weekly_desc")}),
        m(FrequencyToggle,{settingKey:"ernestdefoe-digest-mail.allow_monthly",defaultOn:true, emoji:"🗓️",iconBg:"#dbeafe",label:tr("ernestdefoe-digest-mail.admin.frequency.monthly"),description:tr("ernestdefoe-digest-mail.admin.frequency.monthly_desc")})
      )),
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(app.translator.trans("ernestdefoe-digest-mail.admin.onboarding.section_heading")),
        m(OnboardingSection)
      )),
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.extension_integrations")),
        m("p",{className:"helpText",style:"margin-bottom:12px;"},tr("ernestdefoe-digest-mail.admin.integrations.help")),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_leaderboard",extData:exts.leaderboard||{},description:tr("ernestdefoe-digest-mail.admin.integrations.leaderboard_desc"),installedNote:"huseyinfiliz/leaderboard is installed and active",notInstalledNote:"huseyinfiliz/leaderboard is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_badges",     extData:exts.badges||{},     description:tr("ernestdefoe-digest-mail.admin.integrations.badges_desc"),                                          installedNote:"fof/badges is installed and active",              notInstalledNote:"fof/badges is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_pickem",     extData:exts.pickem||{},     description:tr("ernestdefoe-digest-mail.admin.integrations.pickem_desc"),                                                          installedNote:"huseyinfiliz/pickem is installed and active",     notInstalledNote:"huseyinfiliz/pickem is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_picks",      extData:exts.picks||{},      description:tr("ernestdefoe-digest-mail.admin.integrations.picks_desc"), installedNote:"ernestdefoe/picks is installed and active",          notInstalledNote:"ernestdefoe/picks is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_gamepedia",  extData:exts.gamepedia||{},         description:tr("ernestdefoe-digest-mail.admin.integrations.gamepedia_desc"),                                                              installedNote:"huseyinfiliz/gamepedia is installed and active",        notInstalledNote:"huseyinfiliz/gamepedia is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_resofire_gamepedia",extData:exts.resofireGamepedia||{},description:tr("ernestdefoe-digest-mail.admin.integrations.resofire_gamepedia_desc"),                                 installedNote:"resofire/gamepedia is installed and active",            notInstalledNote:"resofire/gamepedia is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_reactions",  extData:exts.reactions||{},  description:tr("ernestdefoe-digest-mail.admin.integrations.reactions_desc"),installedNote:"fof/reactions is installed and active",notInstalledNote:"fof/reactions is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_awards",     extData:exts.awards||{},     description:tr("ernestdefoe-digest-mail.admin.integrations.awards_desc"),installedNote:"huseyinfiliz/awards is installed and active",notInstalledNote:"huseyinfiliz/awards is not installed or is disabled"}),
        m("div",{style:"margin-top:24px;padding-top:20px;border-top:1px solid var(--control-bg);margin-bottom:8px;"},
          m("p",{style:"font-size:12px;color:var(--muted-color);margin:0;"},
            tr("ernestdefoe-digest-mail.admin.integrations.favorites_auto_note_before"),
            m("strong",null,"flarum/likes"),
            tr("ernestdefoe-digest-mail.admin.integrations.favorites_auto_note_after")
          )
        ),
        m("div",{style:"display:flex;align-items:center;gap:16px;padding:16px 20px;border-radius:8px;background:var(--control-bg);border:1px solid var(--control-bg);margin-bottom:10px;opacity:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"1":"0.55")+";"},
          m(ExtIcon,{iconName:"fas fa-heart",iconColor:"#fff",iconBg:"#e11d48",size:44}),
          m("div",{style:"flex:1;min-width:0;"},
            m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:3px;"},
              m("span",{style:"font-size:16px;font-weight:700;color:var(--heading-color,var(--text-color));"},tr("ernestdefoe-digest-mail.admin.integrations.favorites_title")),
              m("span",{style:"font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;background:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"rgba(34,197,94,.15)":"var(--control-bg)")+";color:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"#16a34a":"var(--muted-color);")+";"},!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?tr("ernestdefoe-digest-mail.admin.integrations.status_active"):tr("ernestdefoe-digest-mail.admin.integrations.status_inactive"))),
            m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.45;margin-bottom:4px;"},tr("ernestdefoe-digest-mail.admin.integrations.favorites_desc")),
            m("div",{style:"font-size:12px;color:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"#16a34a":"var(--muted-color);")+";"},
              !!(exts.reactions||{}).enabled&&!!(exts.likes||{}).enabled?tr("ernestdefoe-digest-mail.admin.integrations.favorites_status_both"):!!(exts.likes||{}).enabled?tr("ernestdefoe-digest-mail.admin.integrations.favorites_status_likes"):tr("ernestdefoe-digest-mail.admin.integrations.favorites_status_disabled")))
        )
      )),
      m(TokenCheckerSection),
      m(TestSendSection)
    );
  }
};

let DigestOrderTab={
  oninit:function(vnode){
    let saved=getSettingVal("ernestdefoe-digest-mail.section_order","");
    let order=[];try{order=saved?JSON.parse(saved):[];}catch(e){order=[];}
    if(!order.length)order=DEFAULT_ORDER.slice();
    vnode.state.order=order;vnode.state.saving=false;vnode.state.saved=false;
  },
  activeSections:function(order){
    let exts=(app.forum.attribute("digestExtensions"))||{};
    let integrationEnabled={
      leaderboard:       getSettingVal("ernestdefoe-digest-mail.enable_leaderboard","1")==="1"&&!!(exts.leaderboard||{}).enabled,
      badges:            getSettingVal("ernestdefoe-digest-mail.enable_badges","1")==="1"     &&!!(exts.badges||{}).enabled,
      pickem:            getSettingVal("ernestdefoe-digest-mail.enable_pickem","1")==="1"     &&!!(exts.pickem||{}).enabled,
      picks:             getSettingVal("ernestdefoe-digest-mail.enable_picks","1")==="1"      &&!!(exts.picks||{}).enabled,
      gamepedia:         getSettingVal("ernestdefoe-digest-mail.enable_gamepedia","1")==="1"  &&!!(exts.gamepedia||{}).enabled,
      resofireGamepedia: getSettingVal("ernestdefoe-digest-mail.enable_resofire_gamepedia","1")==="1"&&!!(exts.resofireGamepedia||{}).enabled,
      favorites:         (parseInt(getSettingVal("ernestdefoe-digest-mail.limit_favorites","6"),10)>0)&&(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled),
      awards:            getSettingVal("ernestdefoe-digest-mail.enable_awards","1")==="1"     &&!!(exts.awards||{}).enabled,
    };
    let allSections={};
    FIXED_SECTIONS.forEach(function(s){allSections[s.key]=s;});
    Object.keys(INTEGRATION_SECTIONS).forEach(function(k){allSections[k]=INTEGRATION_SECTIONS[k];});
    let active=order.filter(function(key){
      if(!allSections[key])return false;
      if(INTEGRATION_SECTIONS[key])return integrationEnabled[key]||false;
      return true;
    });
    Object.keys(INTEGRATION_SECTIONS).forEach(function(key){
      if(integrationEnabled[key]&&active.indexOf(key)===-1)active.push(key);
    });
    return active.map(function(key){return allSections[key];});
  },
  move:function(vnode,index,direction){
    let order=vnode.state.order.slice();
    let sections=DigestOrderTab.activeSections(order);
    let newIdx=index+direction;
    if(newIdx<0||newIdx>=sections.length)return;
    let keyA=sections[index].key;let keyB=sections[newIdx].key;
    let iA=order.indexOf(keyA);let iB=order.indexOf(keyB);
    if(iA===-1){order.push(keyA);iA=order.length-1;}
    if(iB===-1){order.push(keyB);iB=order.length-1;}
    let tmp=order[iA];order[iA]=order[iB];order[iB]=tmp;
    vnode.state.order=order;vnode.state.saved=false;vnode.state.saving=true;
    saveSetting("ernestdefoe-digest-mail.section_order",JSON.stringify(order)).then(function(){
      vnode.state.saving=false;vnode.state.saved=true;
      setTimeout(function(){vnode.state.saved=false;m.redraw();},5000);
      m.redraw();
    });
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k){return app.translator.trans(k);};
    let sections=DigestOrderTab.activeSections(s.order);
    let isFixed=function(key){return FIXED_SECTIONS.some(function(f){return f.key===key;});};
    return m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
      m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},tr("ernestdefoe-digest-mail.admin.sections.digest_section_order")),
      m("p",{className:"helpText",style:"margin-bottom:16px;"},tr("ernestdefoe-digest-mail.admin.order.help")),
      sections.length===0
        ?m("p",{style:"color:var(--muted-color);font-size:14px;"},tr("ernestdefoe-digest-mail.admin.order.no_sections_active"))
        :sections.map(function(section,index){
          let fixed=isFixed(section.key);
          let isFirst=index===0;let isLast=index===sections.length-1;
          let btnBase="width:30px;height:28px;border:1px solid var(--control-bg);border-radius:5px;background:var(--body-bg);font-size:14px;display:flex;align-items:center;justify-content:center;";
          return m("div",{key:section.key,style:"display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:8px;margin-bottom:8px;background:var(--control-bg);border:1px solid var(--control-bg);"},
            m("div",{style:"font-size:18px;font-weight:700;color:var(--muted-color);width:24px;text-align:center;flex-shrink:0;"},index+1),
            m(ExtIcon,{iconName:section.icon,iconColor:section.iconColor,iconBg:section.iconBg,size:40}),
            m("div",{style:"flex:1;min-width:0;"},
              m("div",{style:"display:flex;align-items:center;gap:8px;"},
                m("span",{style:"font-size:15px;font-weight:700;color:var(--heading-color,var(--text-color));"},section.labelKey?tr(section.labelKey):section.label),
                fixed?m("span",{style:"font-size:10px;font-weight:600;padding:2px 7px;border-radius:20px;background:var(--control-bg);color:var(--muted-color);border:1px solid var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.integrations.always_shown")):null
              )
            ),
            m("div",{style:"display:flex;flex-direction:column;gap:2px;flex-shrink:0;"},
              m("button",{style:btnBase+"cursor:"+(isFirst?"not-allowed":"pointer")+";color:"+(isFirst?"var(--muted-color)":"var(--text-color)"),disabled:isFirst,title:tr("ernestdefoe-digest-mail.admin.order.move_up"),onclick:function(e){e.preventDefault();DigestOrderTab.move(vnode,index,-1);}},"\u25B2"),
              m("button",{style:btnBase+"cursor:"+(isLast?"not-allowed":"pointer")+";color:"+(isLast?"var(--muted-color)":"var(--text-color)"),disabled:isLast,title:tr("ernestdefoe-digest-mail.admin.order.move_down"),onclick:function(e){e.preventDefault();DigestOrderTab.move(vnode,index,1);}},"\u25BC")
            )
          );
        }),
      s.saving?m("p",{style:"font-size:12px;color:var(--muted-color);margin-top:8px;"},tr("ernestdefoe-digest-mail.admin.order.saving")):null,
      s.saved ?m("p",{style:"font-size:12px;color:#16a34a;margin-top:8px;"},"\u2713 "+tr("ernestdefoe-digest-mail.admin.order.order_saved")):null
    ));
  }
};

let SubscriberList={
  oninit:function(vnode){
    vnode.state.loading=false;
    vnode.state.error=null;
    vnode.state.data=null;
    vnode.state.page=1;
  },
  load:function(vnode,page){
    let freq=vnode.attrs.frequency;
    vnode.state.loading=true;
    vnode.state.error=null;
    vnode.state.page=page;
    m.redraw();
    app.request({
      method:"GET",
      url:app.forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/subscribers?frequency="+freq+"&page="+page+"&per_page=15"
    }).then(function(d){
      vnode.state.loading=false;
      vnode.state.data=d;
      m.redraw();
    }).catch(function(e){
      vnode.state.loading=false;
      vnode.state.error=(e&&e.message)||app.translator.trans("ernestdefoe-digest-mail.admin.stats.error_load_subscribers");
      m.redraw();
    });
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k,v){return app.translator.trans(k,v);};
    let color=vnode.attrs.color||"#f59e0b";
    let formatDate=function(str){
      if(!str)return tr("ernestdefoe-digest-mail.admin.stats.never");
      let d=new Date(str.replace(" ","T")+"Z");
      return d.toLocaleDateString(undefined,{month:"short",day:"numeric",year:"numeric"});
    };
    let renderAvatar=function(user){
      if(user.avatar_url){
        return m("img",{src:user.avatar_url,style:"width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;"});
      }
      let initials=(user.username||"?").charAt(0).toUpperCase();
      return m("div",{style:"width:28px;height:28px;border-radius:50%;background:"+color+";display:flex;align-items:center;justify-content:center;flex-shrink:0;"},
        m("span",{style:"font-size:12px;font-weight:700;color:#fff;"},initials)
      );
    };

    if(s.loading){
      return m("div",{style:"padding:16px;text-align:center;color:var(--muted-color);font-size:13px;"},tr("ernestdefoe-digest-mail.admin.stats.loading"));
    }
    if(s.error){
      return m("div",{style:"padding:12px;color:#dc2626;font-size:13px;"},s.error);
    }
    if(!s.data){
      return m("div",{style:"padding:16px;text-align:center;color:var(--muted-color);font-size:13px;"},tr("ernestdefoe-digest-mail.admin.stats.no_data_loaded"));
    }

    let d=s.data;
    let hasPrev=d.page>1;
    let hasNext=d.page<d.total_pages;

    return m("div",{style:"padding:12px 0 4px;"},
      d.data.length===0
        ?m("p",{style:"color:var(--muted-color);font-size:13px;padding:0 18px;margin:0;"},tr("ernestdefoe-digest-mail.admin.stats.no_subscribers_found"))
        :m("div",null,
          d.data.map(function(user){
            return m("div",{key:user.id,style:"display:flex;align-items:center;gap:10px;padding:8px 18px;border-bottom:1px solid var(--control-bg);"},
              renderAvatar(user),
              m("div",{style:"flex:1;min-width:0;"},
                m("span",{style:"font-size:13px;font-weight:600;color:var(--heading-color,var(--text-color));display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"},user.username)
              ),
              m("span",{style:"font-size:11px;color:var(--muted-color);white-space:nowrap;flex-shrink:0;"},
                tr("ernestdefoe-digest-mail.admin.stats.last_sent_prefix")+formatDate(user.last_sent)
              )
            );
          }),
          (hasPrev||hasNext)?m("div",{style:"display:flex;align-items:center;justify-content:space-between;padding:10px 18px 6px;"},
            m("button",{
              style:"font-size:12px;padding:4px 10px;border:1px solid var(--control-bg);border-radius:5px;background:var(--body-bg);color:"+(hasPrev?"var(--text-color)":"var(--muted-color)")+";cursor:"+(hasPrev?"pointer":"not-allowed")+";",
              disabled:!hasPrev,
              onclick:function(e){e.preventDefault();if(hasPrev)SubscriberList.load(vnode,d.page-1);}
            },"\u2190 "+tr("ernestdefoe-digest-mail.admin.stats.prev")),
            m("span",{style:"font-size:12px;color:var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.stats.page_of",{page:d.page,total:d.total_pages,count:d.total})),
            m("button",{
              style:"font-size:12px;padding:4px 10px;border:1px solid var(--control-bg);border-radius:5px;background:var(--body-bg);color:"+(hasNext?"var(--text-color)":"var(--muted-color)")+";cursor:"+(hasNext?"pointer":"not-allowed")+";",
              disabled:!hasNext,
              onclick:function(e){e.preventDefault();if(hasNext)SubscriberList.load(vnode,d.page+1);}
            },tr("ernestdefoe-digest-mail.admin.stats.next")+" \u2192")
          ):null
        )
    );
  }
};

let StatsTab={
  oninit:function(vnode){
    vnode.state.loading=true;
    vnode.state.error=null;
    vnode.state.data=null;
    app.request({method:"GET",url:app.forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/stats"})
      .then(function(d){vnode.state.loading=false;vnode.state.data=d;m.redraw();})
      .catch(function(e){vnode.state.loading=false;vnode.state.error=(e&&e.message)||app.translator.trans("ernestdefoe-digest-mail.admin.stats.error_load_stats");m.redraw();});
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k,v){return app.translator.trans(k,v);};
    let sh=function(t){return m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},t);};
    let card=function(label,value,sub){return m("div",{style:"background:var(--control-bg);border-radius:10px;padding:20px 24px;text-align:center;"},m("div",{style:"font-size:28px;font-weight:800;color:var(--heading-color,var(--text-color));line-height:1;margin-bottom:6px;"},value),m("div",{style:"font-size:13px;font-weight:600;color:var(--muted-color);margin-bottom:sub?4px:0;"},label),sub?m("div",{style:"font-size:12px;color:var(--muted-color);"},sub):null);};
    let freqColor={"daily":"#f59e0b","weekly":"#3b82f6","monthly":"#8b5cf6"};
    let freqLabel={"daily":tr("ernestdefoe-digest-mail.admin.frequency.daily"),"weekly":tr("ernestdefoe-digest-mail.admin.frequency.weekly"),"monthly":tr("ernestdefoe-digest-mail.admin.frequency.monthly")};

    if(s.loading) return m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;padding:40px 0;text-align:center;color:var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.stats.loading_statistics")));
    if(s.error)   return m("div",{className:"ExtensionPage-settings"},m("div",{className:"Alert Alert--error",style:"max-width:600px;margin:0 auto;"},s.error));

    let sub=s.data.subscriptions;
    let lastSent=s.data.last_sent;
    let log=s.data.send_log||[];

    let formatDate=function(str){
      if(!str)return "—";
      let d=new Date(str.replace(" ","T")+"Z");
      return d.toLocaleDateString(undefined,{month:"short",day:"numeric",year:"numeric"})+" at "+d.toLocaleTimeString(undefined,{hour:"2-digit",minute:"2-digit"});
    };

    return m("div",null,
      // Subscription overview cards
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.subscription_overview")),
        m("div",{style:"display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;"},
          card(tr("ernestdefoe-digest-mail.admin.stats.total_members"),   sub.total_members),
          card(tr("ernestdefoe-digest-mail.admin.stats.subscribers"),     sub.total_subscribed),
          card(tr("ernestdefoe-digest-mail.admin.stats.subscription_rate"), sub.subscription_rate+"%", tr("ernestdefoe-digest-mail.admin.stats.of_confirmed_members"))
        )
      )),

      // By frequency breakdown
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.subscribers_by_frequency")),
        m("div",{style:"display:flex;flex-direction:column;gap:10px;"},
          ["daily","weekly","monthly"].map(function(freq){
            let count=sub.by_frequency[freq]||0;
            let pct=sub.total_subscribed>0?Math.round(count/sub.total_subscribed*100):0;
            let color=freqColor[freq];
            let panelKey="panel_"+freq;
            let open=s[panelKey]||false;
            let listKey="list_"+freq;
            return m("div",{key:freq,style:"background:var(--control-bg);border-radius:8px;overflow:hidden;"},
              m("div",{style:"padding:14px 18px;"},
                m("div",{style:"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;"},
                  m("span",{style:"font-size:14px;font-weight:700;color:var(--heading-color,var(--text-color));"},freqLabel[freq]),
                  m("div",{style:"display:flex;align-items:center;gap:10px;"},
                    m("span",{style:"font-size:14px;font-weight:700;color:"+color+";"},tr("ernestdefoe-digest-mail.admin.stats.user_count",{count:count})),
                    count>0?m("button",{
                      style:"font-size:11px;font-weight:600;padding:3px 9px;border-radius:5px;border:1px solid "+color+";background:transparent;color:"+color+";cursor:pointer;white-space:nowrap;",
                      onclick:function(e){
                        e.preventDefault();
                        s[panelKey]=!open;
                        if(!open&&!s[listKey]){
                          if(!s._lists)s._lists={};
                          s._lists[freq]=true;
                        }
                        m.redraw();
                      }
                    },open?tr("ernestdefoe-digest-mail.admin.stats.hide")+" \u25b2":tr("ernestdefoe-digest-mail.admin.stats.view")+" \u25bc"):null
                  )
                ),
                m("div",{style:"height:6px;border-radius:3px;background:var(--body-bg);overflow:hidden;"},
                  m("div",{style:"height:100%;border-radius:3px;background:"+color+";width:"+pct+"%;transition:width .4s;"})
                ),
                m("div",{style:"font-size:11px;color:var(--muted-color);margin-top:5px;text-align:right;"},tr("ernestdefoe-digest-mail.admin.stats.pct_of_subscribers",{pct:pct}))
              ),
              open?m("div",{style:"border-top:1px solid var(--body-bg);"},
                m(SubscriberList,{
                  key:freq,
                  frequency:freq,
                  color:color,
                  oncreate:function(sl){SubscriberList.load(sl,1);}
                })
              ):null
            );
          })
        )
      )),

      // Last sent per frequency
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.last_sent")),
        m("div",{style:"display:flex;flex-direction:column;gap:8px;"},
          ["daily","weekly","monthly"].map(function(freq){
            let color=freqColor[freq];
            let val=formatDate(lastSent[freq]);
            let hasValue=!!lastSent[freq];
            return m("div",{key:freq,style:"display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:var(--control-bg);border-radius:8px;"},
              m("div",{style:"display:flex;align-items:center;gap:10px;"},
                m("div",{style:"width:10px;height:10px;border-radius:50%;background:"+color+";flex-shrink:0;"}),
                m("span",{style:"font-size:14px;font-weight:600;color:var(--heading-color,var(--text-color));"},freqLabel[freq])
              ),
              m("span",{style:"font-size:13px;color:"+(hasValue?"var(--text-color)":"var(--muted-color)")+";"},val)
            );
          })
        )
      )),

      // Send log table
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(tr("ernestdefoe-digest-mail.admin.sections.send_history")),
        log.length===0
          ?m("p",{style:"color:var(--muted-color);font-size:14px;"},tr("ernestdefoe-digest-mail.admin.stats.no_send_history"))
          :m("div",{style:"overflow:hidden;border-radius:8px;border:1px solid var(--control-bg);"},
              m("table",{style:"width:100%;border-collapse:collapse;font-size:13px;"},
                m("thead",null,
                  m("tr",{style:"background:var(--control-bg);"},
                    m("th",{style:"padding:10px 14px;text-align:left;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},tr("ernestdefoe-digest-mail.admin.stats.col_frequency")),
                    m("th",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},tr("ernestdefoe-digest-mail.admin.stats.col_sent")),
                    m("th",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},tr("ernestdefoe-digest-mail.admin.stats.col_skipped")),
                    m("th",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},tr("ernestdefoe-digest-mail.admin.stats.col_datetime"))
                  )
                ),
                m("tbody",null,
                  log.map(function(row,i){
                    let color=freqColor[row.frequency]||"var(--muted-color)";
                    let bg=i%2===0?"var(--body-bg)":"var(--control-bg)";
                    return m("tr",{key:i,style:"background:"+bg+";"},
                      m("td",{style:"padding:10px 14px;"},
                        m("span",{style:"display:inline-flex;align-items:center;gap:6px;"},
                          m("span",{style:"width:8px;height:8px;border-radius:50%;background:"+color+";display:inline-block;flex-shrink:0;"}),
                          m("span",{style:"font-weight:600;color:var(--heading-color,var(--text-color));text-transform:capitalize;"},row.frequency)
                        )
                      ),
                      m("td",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--text-color);"},row.sent_count),
                      m("td",{style:"padding:10px 14px;text-align:right;color:var(--muted-color);"},row.skipped_count||0),
                      m("td",{style:"padding:10px 14px;text-align:right;color:var(--muted-color);white-space:nowrap;"},formatDate(row.sent_at))
                    );
                  })
                )
              )
            )
      ))
    );
  }
};


let ServerTab={
  oninit:function(vnode){
    vnode.state.queueName=getSettingVal("ernestdefoe-digest-mail.queue_name","digest");
    vnode.state.cron=null;
    vnode.state.cronLoaded=false;
    vnode.state.queueType="database";
    vnode.state.redisSubMode="horizon";
    app.request({method:"GET",url:app.forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/stats"})
      .then(function(d){
        vnode.state.cron=(d&&d.cron)?d.cron:null;
        vnode.state.cronLoaded=true;
        m.redraw();
      })
      .catch(function(){vnode.state.cronLoaded=true;m.redraw();});
  },
  view:function(vnode){
    let s=vnode.state;
    let tr=function(k,v){return app.translator.trans(k,v);};
    let sh=function(t){return m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},t);};
    let qn=getSettingVal("ernestdefoe-digest-mail.queue_name","digest");
    let tries=getSettingVal("ernestdefoe-digest-mail.queue_tries","3");
    // Cron / process-manager lines are assembled server-side (admin-only) so the
    // raw filesystem path is never returned as a bare value; `ph` is only the
    // placeholder shown before the stats request resolves.
    let c=s.cron||{};
    let ph="/path/to/flarum";
    let notice=function(icon,title,body,color){
      return m("div",{style:"display:flex;gap:14px;padding:16px 18px;border-radius:8px;background:var(--control-bg);border-left:4px solid "+(color||"var(--primary-color,#4f46e5)")+";margin-bottom:14px;"},
        m("div",{style:"font-size:22px;flex-shrink:0;line-height:1.3;"},icon),
        m("div",{style:"flex:1;min-width:0;"},
          m("div",{style:"font-size:13px;font-weight:700;color:var(--heading-color,var(--text-color));margin-bottom:4px;"},title),
          m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.6;"},body)
        )
      );
    };
    let code=function(t){return m("code",{style:"background:var(--body-bg);padding:2px 6px;border-radius:4px;font-size:12px;font-family:monospace;word-break:break-all;"},t);};
    let cronBlock=function(label,text){
      return m("div",{style:"margin-bottom:16px;"},
        m("div",{style:"font-size:13px;font-weight:600;color:var(--heading-color,var(--text-color));margin-bottom:6px;"},label),
        m("div",{style:"position:relative;"},
          m("pre",{style:"background:var(--body-bg);border:1px solid var(--control-bg);border-radius:6px;padding:10px 44px 10px 14px;font-family:monospace;font-size:12px;color:var(--text-color);word-break:break-all;line-height:1.7;white-space:pre-wrap;margin:0;"},text),
          m("button",{
            title:tr("ernestdefoe-digest-mail.admin.server.copy_to_clipboard"),
            style:"position:absolute;top:8px;right:8px;background:var(--control-bg);border:1px solid var(--control-bg);border-radius:4px;cursor:pointer;padding:4px 6px;font-size:13px;color:var(--muted-color);line-height:1;",
            onclick:function(e){
              navigator.clipboard&&navigator.clipboard.writeText(text).then(function(){
                let btn=e.target.closest("button")||e.target;
                let prev=btn.textContent;
                btn.textContent="\u2713";
                btn.style.color="var(--success-color,#10b981)";
                setTimeout(function(){btn.textContent=prev;btn.style.color="var(--muted-color)";},1500);
              });
            }
          },"\uD83D\uDCCB")
        )
      );
    };
    let tbl_head=[tr("ernestdefoe-digest-mail.admin.server.tbl_head_forum_size"),tr("ernestdefoe-digest-mail.admin.server.tbl_head_chunk_size"),tr("ernestdefoe-digest-mail.admin.server.tbl_head_workers"),tr("ernestdefoe-digest-mail.admin.server.tbl_head_tries"),tr("ernestdefoe-digest-mail.admin.server.tbl_head_send_mode"),tr("ernestdefoe-digest-mail.admin.server.tbl_head_cron_strategy")];
    let tbl_rows=[
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r1_size"),   "200",   "1", "2", tr("ernestdefoe-digest-mail.admin.server.tbl_mode_single_hour"),   tr("ernestdefoe-digest-mail.admin.server.tbl_r1_strategy")],
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r2_size"),         "500",   "1", "3", tr("ernestdefoe-digest-mail.admin.server.tbl_mode_single_hour"),   tr("ernestdefoe-digest-mail.admin.server.tbl_r2_strategy")],
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r3_size"),       "1000",  "2", "3", tr("ernestdefoe-digest-mail.admin.server.tbl_r3_mode"), tr("ernestdefoe-digest-mail.admin.server.tbl_r3_strategy")],
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r4_size"),      "2000",  "3", "3", tr("ernestdefoe-digest-mail.admin.server.tbl_r4_mode"), tr("ernestdefoe-digest-mail.admin.server.tbl_r4_strategy")],
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r5_size"),     "5000",  "5", "3", tr("ernestdefoe-digest-mail.admin.server.tbl_r5_mode"), tr("ernestdefoe-digest-mail.admin.server.tbl_r5_strategy")],
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r6_size"),    "7500",  "8", "3", tr("ernestdefoe-digest-mail.admin.server.tbl_r6_mode"), tr("ernestdefoe-digest-mail.admin.server.tbl_r6_strategy")],
      [tr("ernestdefoe-digest-mail.admin.server.tbl_r7_size"),          "10000", "10","3", tr("ernestdefoe-digest-mail.admin.server.tbl_r7_mode"),  tr("ernestdefoe-digest-mail.admin.server.tbl_r7_strategy")],
    ];

    // ---- cron line strings (live values) ------------------------------------
    let lineScheduler = c.scheduler || ("* * * * * cd "+ph+" && php flarum schedule:run >> /dev/null 2>&1");
    let lineWorker    = c.worker || ("* * * * * cd "+ph+" && php flarum queue:work --queue="+qn+",default --max-time=55 --tries="+tries+" --backoff=30 >> /dev/null 2>&1");
    let lineWorkers3  = "# Add one line per worker \u2014 e.g. 3 workers:\n"+lineWorker+"\n"+lineWorker+"\n"+lineWorker;
    let lineEnqueue   = "# Optional: pre-build jobs before the window opens (large forums only):\n"+(c.enqueue || ("50 12 * * * cd "+ph+" && php flarum digest:enqueue --frequency=daily --delay=600 >> /dev/null 2>&1"));
    let supervisorConf= c.supervisor || ("[program:flarum-worker]\ncommand=php "+ph+"/flarum queue:work --queue="+qn+",default --tries="+tries+" --backoff=30\ndirectory="+ph+"\nautostart=true\nautorestart=true\nnumprocs=2\nstopwaitsecs=60\nuser=www-data\nredirect_stderr=true\nstdout_logfile="+ph+"/storage/logs/worker.log");
    let horizonConf= c.horizon || ("[program:horizon]\nprocess_name=%(program_name)s\ncommand=php "+ph+"/flarum horizon\nautostart=true\nautorestart=true\nuser=www-data\nredirect_stderr=true\nstdout_logfile="+ph+"/storage/logs/horizon.log\nstopwaitsecs=3600");

    // ---- queue type toggle --------------------------------------------------
    let toggleBtn=function(label,val){
      let active=s.queueType===val;
      return m("button",{
        style:"padding:6px 18px;font-size:12px;font-weight:600;border:1px solid var(--primary-color,#4f46e5);border-radius:4px;cursor:pointer;"
              +(active?"background:var(--primary-color,#4f46e5);color:#fff;":"background:transparent;color:var(--primary-color,#4f46e5);"),
        onclick:function(){s.queueType=val;m.redraw();}
      },label);
    };

    // ---- path status badge --------------------------------------------------
    let pathBadge=!s.cronLoaded
      ? m("span",{style:"font-size:11px;color:var(--muted-color);margin-left:8px;"},tr("ernestdefoe-digest-mail.admin.server.path_loading"))
      : s.cron
        ? m("span",{style:"font-size:11px;color:var(--success-color,#10b981);margin-left:8px;"},"\u2713 "+tr("ernestdefoe-digest-mail.admin.server.path_detected"))
        : m("span",{style:"font-size:11px;color:#f59e0b;margin-left:8px;"},"\u26a0\ufe0f "+tr("ernestdefoe-digest-mail.admin.server.path_unavailable",{path:ph}));

    return m("div",null,
      // ---- Queue Settings --------------------------------------------------
      m("div",{className:"ExtensionPage-settings"},
        m("div",{style:"max-width:660px;margin:0 auto;"},
          sh(tr("ernestdefoe-digest-mail.admin.sections.queue_settings")),
          m("div",{className:"Form-group",style:"margin-bottom:20px;"},
            m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},tr("ernestdefoe-digest-mail.admin.server.queue_name_label")),
            m("p",{className:"helpText",style:"margin-bottom:6px;"},tr("ernestdefoe-digest-mail.admin.server.queue_name_help_1"),code("digest"),tr("ernestdefoe-digest-mail.admin.server.queue_name_help_2"),code("queue:work"),tr("ernestdefoe-digest-mail.admin.server.queue_name_help_3"),code("extend.php"),tr("ernestdefoe-digest-mail.admin.server.queue_name_help_4")),
            m("div",{style:"display:flex;align-items:center;gap:8px;"},
              m("input",{className:"FormControl",type:"text",value:s.queueName,style:"width:200px;",
                oninput:function(e){s.queueName=e.target.value;},
                onblur:function(e){saveSetting("ernestdefoe-digest-mail.queue_name",e.target.value.trim()||"digest");}
              })
            )
          ),
          m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.queue_chunk_size",min:50,max:10000,label:tr("ernestdefoe-digest-mail.admin.settings.queue_chunk_size_label"),help:tr("ernestdefoe-digest-mail.admin.settings.queue_chunk_size_help")}),
          m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.queue_delay",min:0,max:3600,label:tr("ernestdefoe-digest-mail.admin.settings.queue_delay_label"),help:tr("ernestdefoe-digest-mail.admin.settings.queue_delay_help")}),
          m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.queue_tries",min:1,max:10,label:tr("ernestdefoe-digest-mail.admin.settings.queue_tries_label"),help:tr("ernestdefoe-digest-mail.admin.settings.queue_tries_help")})
        )
      ),
      // ---- Cron Setup ------------------------------------------------------
      m("div",{className:"ExtensionPage-settings"},
        m("div",{style:"max-width:660px;margin:0 auto;"},
          sh(tr("ernestdefoe-digest-mail.admin.sections.cron_setup")),
          notice("\u26a0\ufe0f",tr("ernestdefoe-digest-mail.admin.server.crontab_notice_title"),
            m("div",null,
              m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.crontab_notice_body_1"),code("sudo crontab -u YOUR_WEB_USER -e"),tr("ernestdefoe-digest-mail.admin.server.crontab_notice_body_2"),code("YOUR_WEB_USER"),tr("ernestdefoe-digest-mail.admin.server.crontab_notice_body_3")),
              m("ul",{style:"margin:0 0 8px;padding-left:18px;"},
                m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"Ubuntu/Debian"),tr("ernestdefoe-digest-mail.admin.server.crontab_li_ubuntu"),code("www-data")),
                m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"CentOS/RHEL"),tr("ernestdefoe-digest-mail.admin.server.crontab_li_centos_apache"),code("apache")),
                m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"CentOS/RHEL"),tr("ernestdefoe-digest-mail.admin.server.crontab_li_centos_nginx"),code("nginx")),
                m("li",{style:"margin-bottom:0;line-height:1.5;"},m("strong",null,tr("ernestdefoe-digest-mail.admin.server.crontab_li_unsure_label")),tr("ernestdefoe-digest-mail.admin.server.crontab_li_unsure_1"),code("ls -la"),tr("ernestdefoe-digest-mail.admin.server.crontab_li_unsure_2"))
              )
            ),
            "#f59e0b"
          ),
          // Queue type toggle
          m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:20px;"},
            m("span",{style:"font-size:12px;font-weight:600;color:var(--muted-color);margin-right:4px;"},tr("ernestdefoe-digest-mail.admin.server.queue_backend_label")),
            toggleBtn(tr("ernestdefoe-digest-mail.admin.server.backend_sync"),"sync"),
            toggleBtn(tr("ernestdefoe-digest-mail.admin.server.backend_database"),"database"),
            toggleBtn(tr("ernestdefoe-digest-mail.admin.server.backend_redis"),"redis"),
            pathBadge
          ),
          // ---- SYNC MODE --------------------------------------------------
          s.queueType==="sync"?m("div",null,
            notice("\u2705",tr("ernestdefoe-digest-mail.admin.server.sync_no_setup_title"),
              m("div",null,
                m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.sync_no_setup_1a"),code("sync"),tr("ernestdefoe-digest-mail.admin.server.sync_no_setup_1b")),
                m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.sync_no_setup_2a"),code("digest:send"),tr("ernestdefoe-digest-mail.admin.server.sync_no_setup_2b"))
              ),
              "#10b981"
            ),
            cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_scheduler_only_label"),lineScheduler),
            notice("\u26a0\ufe0f",tr("ernestdefoe-digest-mail.admin.server.sync_limits_title"),
              m("div",null,
                m("ul",{style:"margin:0 0 10px;padding-left:18px;"},
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,tr("ernestdefoe-digest-mail.admin.server.sync_limit_under50_label")),tr("ernestdefoe-digest-mail.admin.server.sync_limit_under50")),
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,tr("ernestdefoe-digest-mail.admin.server.sync_limit_50_200_label")),tr("ernestdefoe-digest-mail.admin.server.sync_limit_50_200")),
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,tr("ernestdefoe-digest-mail.admin.server.sync_limit_200_label")),tr("ernestdefoe-digest-mail.admin.server.sync_limit_200")),
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,tr("ernestdefoe-digest-mail.admin.server.sync_limit_500_label")),tr("ernestdefoe-digest-mail.admin.server.sync_limit_500"))
                ),
                m("p",{style:"margin:0 0 6px;"},tr("ernestdefoe-digest-mail.admin.server.sync_upgrade_prompt"),code("config.php"),":"),
                m("pre",{style:"background:var(--body-bg);border:1px solid var(--control-bg);border-radius:6px;padding:10px 14px;font-family:monospace;font-size:12px;color:var(--text-color);margin:0;line-height:1.7;"},
                  "'queue' => [\n    'driver' => 'database',\n],"
                ),
                m("p",{style:"margin:8px 0 0;"},tr("ernestdefoe-digest-mail.admin.server.sync_switch_db_1"),m("strong",null,tr("ernestdefoe-digest-mail.admin.server.backend_database")),tr("ernestdefoe-digest-mail.admin.server.sync_switch_db_2"))
              ),
              "#f59e0b"
            )
          ):null,
          // ---- DATABASE MODE -----------------------------------------------
          s.queueType==="database"?m("div",null,
            notice("\u2139\ufe0f",tr("ernestdefoe-digest-mail.admin.server.db_enable_title"),
              m("div",null,
                m("p",{style:"margin:0 0 6px;"},tr("ernestdefoe-digest-mail.admin.server.db_enable_1a"),code("config.php"),tr("ernestdefoe-digest-mail.admin.server.db_enable_1b")),
                m("pre",{style:"background:var(--body-bg);border:1px solid var(--control-bg);border-radius:6px;padding:10px 14px;font-family:monospace;font-size:12px;color:var(--text-color);margin:0;line-height:1.7;"},
                  "'queue' => [\n    'driver' => 'database',\n],"
                ),
                m("p",{style:"margin:8px 0 0;"},tr("ernestdefoe-digest-mail.admin.server.db_enable_2"))
              ),
              "#3b82f6"
            ),
            cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_scheduler_required_label"),lineScheduler),
            cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_worker_required_label"),lineWorker),
            m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
              code("--queue="+qn+",default"),tr("ernestdefoe-digest-mail.admin.server.worker_flags_1"),
              code("--max-time=55"),tr("ernestdefoe-digest-mail.admin.server.worker_flags_2"),
              code("--tries="+tries),tr("ernestdefoe-digest-mail.admin.server.worker_flags_3"),code("--backoff=30"),tr("ernestdefoe-digest-mail.admin.server.worker_flags_4")
            ),
            notice("\uD83D\uDD04",tr("ernestdefoe-digest-mail.admin.server.retries_title"),
              m("div",null,
                m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.retries_db_body")),
                m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.retries_failed_1"),code("failed_jobs"),tr("ernestdefoe-digest-mail.admin.server.retries_failed_2"),code("php flarum queue:retry all"),".")
              ),
              "#f59e0b"
            ),
            notice("\uD83E\uDE9F",tr("ernestdefoe-digest-mail.admin.server.send_window_title"),
              m("div",null,
                m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.send_window_db_1")),
                m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.send_window_db_2"))
              ),
              "#6366f1"
            ),
            cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_parallel_label"),lineWorkers3),
            m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
              tr("ernestdefoe-digest-mail.admin.server.parallel_db_note")
            ),
            cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_prepop_label"),lineEnqueue),
            m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
              tr("ernestdefoe-digest-mail.admin.server.prepop_note")
            ),
            notice("\uD83D\uDCA1",tr("ernestdefoe-digest-mail.admin.server.db_upgrade_redis_title"),
              m("div",null,
                m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.db_upgrade_redis_1"),m("strong",null,tr("ernestdefoe-digest-mail.admin.server.backend_redis")),tr("ernestdefoe-digest-mail.admin.server.db_upgrade_redis_2"))
              ),
              "#6366f1"
            )
          ):null,
          // ---- REDIS / VALKEY MODE -----------------------------------------
          s.queueType==="redis"?m("div",null,
            notice("\u2139\ufe0f",tr("ernestdefoe-digest-mail.admin.server.redis_installed_title"),
              m("div",null,
                m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.redis_installed_1"),code("fof/redis"),tr("ernestdefoe-digest-mail.admin.server.redis_installed_2"))
              ),
              "#3b82f6"
            ),
            m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:20px;"},
              m("span",{style:"font-size:12px;font-weight:600;color:var(--muted-color);margin-right:4px;"},tr("ernestdefoe-digest-mail.admin.server.worker_approach_label")),
              (function(){
                let subActive=s.redisSubMode==="cron";
                return m("button",{
                  style:"padding:6px 18px;font-size:12px;font-weight:600;border:1px solid var(--primary-color,#4f46e5);border-radius:4px;cursor:pointer;"+(subActive?"background:var(--primary-color,#4f46e5);color:#fff;":"background:transparent;color:var(--primary-color,#4f46e5);"),
                  onclick:function(){s.redisSubMode="cron";m.redraw();}
                },tr("ernestdefoe-digest-mail.admin.server.worker_cron_based"));
              })(),
              (function(){
                let subActive=s.redisSubMode==="horizon";
                return m("button",{
                  style:"padding:6px 18px;font-size:12px;font-weight:600;border:1px solid var(--primary-color,#4f46e5);border-radius:4px;cursor:pointer;"+(subActive?"background:var(--primary-color,#4f46e5);color:#fff;":"background:transparent;color:var(--primary-color,#4f46e5);"),
                  onclick:function(){s.redisSubMode="horizon";m.redraw();}
                },tr("ernestdefoe-digest-mail.admin.server.worker_horizon_recommended"));
              })()
            ),
            // ---- REDIS CRON SUB-MODE ----------------------------------------
            s.redisSubMode==="cron"?m("div",null,
              notice("\u2699\ufe0f",tr("ernestdefoe-digest-mail.admin.server.redis_how_title"),
                m("div",null,
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.redis_how_1a"),code("digest:send"),tr("ernestdefoe-digest-mail.admin.server.redis_how_1b"))
                ),
                "#3b82f6"
              ),
              cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_scheduler_required_label"),lineScheduler),
              cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_worker_required_label"),lineWorker),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
                code("--queue="+qn+",default"),tr("ernestdefoe-digest-mail.admin.server.worker_flags_1"),
                code("--max-time=55"),tr("ernestdefoe-digest-mail.admin.server.redis_worker_flags_2"),code("BLPOP"),tr("ernestdefoe-digest-mail.admin.server.redis_worker_flags_3")
              ),
              notice("\uD83D\uDD04",tr("ernestdefoe-digest-mail.admin.server.retries_title"),
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.retries_redis_body")),
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.retries_failed_redis_1"),code("failed_jobs"),tr("ernestdefoe-digest-mail.admin.server.retries_failed_redis_2"),code("php flarum queue:retry all"),".")
                ),
                "#f59e0b"
              ),
              notice("\uD83E\uDE9F",tr("ernestdefoe-digest-mail.admin.server.send_window_title"),
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.send_window_redis_1")),
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.send_window_redis_2"))
                ),
                "#6366f1"
              ),
              cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_parallel_label"),lineWorkers3),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
                tr("ernestdefoe-digest-mail.admin.server.parallel_redis_note")
              ),
              cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_prepop_label"),lineEnqueue),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
                tr("ernestdefoe-digest-mail.admin.server.prepop_note")
              ),
              notice("\uD83D\uDCA1",tr("ernestdefoe-digest-mail.admin.server.redis_upgrade_horizon_title"),
                m("div",null,
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.redis_upgrade_horizon_1"),m("strong",null,tr("ernestdefoe-digest-mail.admin.server.worker_horizon_plain")),tr("ernestdefoe-digest-mail.admin.server.redis_upgrade_horizon_2"))
                ),
                "#6366f1"
              )
            ):null,
            // ---- REDIS HORIZON SUB-MODE -------------------------------------
            s.redisSubMode==="horizon"?m("div",null,
              notice("\u2705",tr("ernestdefoe-digest-mail.admin.server.horizon_recommended_title"),
                m("div",null,
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.horizon_recommended_1a"),code("/admin/horizon"),tr("ernestdefoe-digest-mail.admin.server.horizon_recommended_1b"))
                ),
                "#10b981"
              ),
              sh(tr("ernestdefoe-digest-mail.admin.server.horizon_step1")),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step1_run")),
              cronBlock("",("composer require fof/horizon")),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.server.horizon_step1_enable_1"),code("FoF Horizon"),tr("ernestdefoe-digest-mail.admin.server.horizon_step1_enable_2")),
              sh(tr("ernestdefoe-digest-mail.admin.server.horizon_step2")),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step2_1"),code("extend.php"),tr("ernestdefoe-digest-mail.admin.server.horizon_step2_2"),code("digest"),tr("ernestdefoe-digest-mail.admin.server.horizon_step2_3"),code("default"),tr("ernestdefoe-digest-mail.admin.server.horizon_step2_4")),
              cronBlock("extend.php",("<?php\n\nuse FoF\\Redis\\Extend\\Redis;\nuse FoF\\Horizon\\Extend\\Horizon;\n\nreturn [\n    new Redis([\n        'host'     => '127.0.0.1',\n        'password' => null,\n        'port'     => 6379,\n        'database' => 0,\n    ]),\n\n    (new Horizon)->environment([\n        'supervisor-1' => [\n            'connection' => 'redis',\n            'queue'      => ['"+qn+"', 'default'],\n            'balance'    => 'auto',\n            'processes'  => 4,\n            'tries'      => "+tries+",\n            'memory'     => 128,\n        ],\n    ]),\n];")),
              sh(tr("ernestdefoe-digest-mail.admin.server.horizon_step3")),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step3_1"),code("/etc/supervisor/conf.d/horizon.conf"),":"),
              cronBlock("/etc/supervisor/conf.d/horizon.conf",horizonConf),
              m("p",{style:"margin:-8px 0 8px;font-size:12px;color:var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.server.horizon_step3_adjust_1"),code("user"),tr("ernestdefoe-digest-mail.admin.server.horizon_step3_adjust_2"),code("www-data"),", ",code("apache"),", ",code("nginx"),tr("ernestdefoe-digest-mail.admin.server.horizon_step3_adjust_3")),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step3_load")),
              cronBlock("",("sudo supervisorctl reread\nsudo supervisorctl update\nsudo supervisorctl start horizon\nsudo supervisorctl status")),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},tr("ernestdefoe-digest-mail.admin.server.horizon_step3_seesuccess_1"),code("horizon"),tr("ernestdefoe-digest-mail.admin.server.horizon_step3_seesuccess_2"),code("RUNNING"),"."),
              sh(tr("ernestdefoe-digest-mail.admin.server.horizon_step4")),
              notice("\u26a0\ufe0f",tr("ernestdefoe-digest-mail.admin.server.horizon_step4_notice_title"),
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step4_notice_1a"),code(qn),tr("ernestdefoe-digest-mail.admin.server.horizon_step4_notice_1b"),code("default"),tr("ernestdefoe-digest-mail.admin.server.horizon_step4_notice_1c"),code("queue:work"),tr("ernestdefoe-digest-mail.admin.server.horizon_step4_notice_1d")),
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step4_notice_2"))
                ),
                "#f59e0b"
              ),
              cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_scheduler_only_label"),lineScheduler),
              sh(tr("ernestdefoe-digest-mail.admin.server.horizon_step5")),
              m("p",{style:"margin:0 0 16px;font-size:13px;color:var(--muted-color);line-height:1.6;"},tr("ernestdefoe-digest-mail.admin.server.horizon_step5_1a"),code("/admin/horizon"),tr("ernestdefoe-digest-mail.admin.server.horizon_step5_1b"),m("strong",null,tr("ernestdefoe-digest-mail.admin.server.horizon_step5_running")),tr("ernestdefoe-digest-mail.admin.server.horizon_step5_1c"),code("supervisor-1"),tr("ernestdefoe-digest-mail.admin.server.horizon_step5_1d"),code(qn),tr("ernestdefoe-digest-mail.admin.server.horizon_step5_1e"),code("default"),tr("ernestdefoe-digest-mail.admin.server.horizon_step5_1f")),
              notice("\uD83E\uDE9F",tr("ernestdefoe-digest-mail.admin.server.send_window_title"),
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},tr("ernestdefoe-digest-mail.admin.server.send_window_horizon_1")),
                  m("p",{style:"margin:0;"},tr("ernestdefoe-digest-mail.admin.server.send_window_redis_2"))
                ),
                "#6366f1"
              ),
              cronBlock(tr("ernestdefoe-digest-mail.admin.server.cron_prepop_horizon_label"),lineEnqueue),
              m("p",{style:"margin:-8px 0 0;font-size:12px;color:var(--muted-color);"},
                tr("ernestdefoe-digest-mail.admin.server.prepop_horizon_note")
              )
            ):null
          ):null
        )
      ),
      // ---- Recommended Settings by Forum Size ------------------------------
      m("div",{className:"ExtensionPage-settings"},
        m("div",{style:"max-width:660px;margin:0 auto;"},
          sh(tr("ernestdefoe-digest-mail.admin.sections.recommended_settings")),
          m("p",{style:"margin:0 0 16px;font-size:13px;color:var(--muted-color);line-height:1.6;"},
            tr("ernestdefoe-digest-mail.admin.server.recommended_intro")
          ),
          m("div",{style:"overflow:hidden;border-radius:8px;border:1px solid var(--control-bg);"},
            m("table",{style:"width:100%;border-collapse:collapse;font-size:12px;"},
              m("thead",null,
                m("tr",{style:"background:var(--control-bg);"},
                  tbl_head.map(function(h){
                    return m("th",{style:"padding:10px 12px;text-align:left;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;"},h);
                  })
                )
              ),
              m("tbody",null,
                tbl_rows.map(function(row,i){
                  let bg=i%2===0?"var(--body-bg)":"var(--control-bg)";
                  return m("tr",{style:"background:"+bg+";"},
                    row.map(function(cell,ci){
                      return m("td",{style:"padding:10px 12px;color:"+(ci===0?"var(--heading-color,var(--text-color))":"var(--muted-color))")+";font-weight:"+(ci===0?"600":"400")+";vertical-align:top;line-height:1.5;"},
                        ci<=3?m("code",{style:"background:var(--control-bg);padding:1px 5px;border-radius:3px;font-size:11px;"},cell):cell
                      );
                    })
                  );
                })
              )
            )
          ),
          m("div",{style:"margin-top:12px;padding:12px 16px;background:var(--control-bg);border-radius:8px;"},
            m("p",{style:"margin:0;font-size:12px;color:var(--muted-color);line-height:1.6;"},
              "\uD83D\uDCA1 "+tr("ernestdefoe-digest-mail.admin.server.recommended_tip")
            )
          )
        )
      )
    );
  }
};


let DigestAdminPage={
  oninit:function(vnode){vnode.state.tab="settings";},
  view:function(vnode){
    let s=vnode.state;
    let tabStyle=function(active){
      return "padding:10px 24px;font-size:14px;font-weight:600;border:none;cursor:pointer;"
            +"border-bottom:3px solid "+(active?"var(--primary-color,#4f46e5)":"transparent")+";"
            +"color:"+(active?"var(--primary-color,#4f46e5)":"var(--muted-color)")+";"
            +"background:transparent;transition:color .15s,border-color .15s;";
    };
    return m("div",null,
      m("div",{style:"display:flex;justify-content:center;border-bottom:1px solid var(--control-bg);margin-bottom:0;"},
        m("button",{style:tabStyle(s.tab==="settings"), onclick:function(){s.tab="settings";m.redraw();}},  "\u2699\uFE0F Settings"),
        m("button",{style:tabStyle(s.tab==="order"),    onclick:function(){s.tab="order";m.redraw();}},     "\u2195 Digest Order"),
        m("button",{style:tabStyle(s.tab==="stats"),    onclick:function(){s.tab="stats";m.redraw();}},     "\uD83D\uDCCA Statistics"),
        m("button",{style:tabStyle(s.tab==="server"),   onclick:function(){s.tab="server";m.redraw();}},    "\uD83D\uDDA5\uFE0F Server Settings")
      ),
      s.tab==="settings"?m(SettingsTab):s.tab==="order"?m(DigestOrderTab):s.tab==="stats"?m(StatsTab):m(ServerTab)
    );
  }
};

app.initializers.add("ernestdefoe-digest-mail",function(){
  let style=document.createElement("style");
  style.textContent=".Select-input.FormControl{line-height:1.4 !important;padding-bottom:8px !important;height:auto !important;}";
  document.head.appendChild(style);
  app.registry.for("ernestdefoe-digest-mail").registerSetting(function(){return m(DigestAdminPage);},100);
});

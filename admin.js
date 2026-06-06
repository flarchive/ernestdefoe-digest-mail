(()=>{var t={n:o=>{var s=o&&o.__esModule?()=>o.default:()=>o;return t.d(s,{a:s}),s},d:(o,s)=>{for(var n in s)t.o(s,n)&&!t.o(o,n)&&Object.defineProperty(o,n,{enumerable:!0,get:s[n]})},o:(t,o)=>Object.prototype.hasOwnProperty.call(t,o)},o={};(()=>{"use strict";
const _app=flarum.reg.get("core","admin/app");var app=t.n(_app);

function ordinal(n){var s=["th","st","nd","rd"];var v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}
function saveSetting(key,val){return app().request({method:"POST",url:app().forum.attribute("apiUrl")+"/settings",body:{[key]:val}}).then(function(){app().data.settings[key]=val;});}
function getSettingVal(key,fallback){var v=app().data.settings[key];return(v===undefined||v===null)?fallback:v;}

var TIMEZONES=[
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
function tzOffsetLabel(tz){try{var fmt=new Intl.DateTimeFormat("en-US",{timeZone:tz,timeZoneName:"shortOffset"});var parts=fmt.formatToParts(new Date());var p=parts.find(function(x){return x.type==="timeZoneName";});return p?p.value.replace("GMT","UTC"):"UTC";}catch(e){return "UTC";}}
function buildHourOptions(tz){var opts={};var label=tzOffsetLabel(tz);for(var h=0;h<24;h++){var padded=h<10?"0"+h:""+h;var lbl=padded+":00 "+label;if(h===0)lbl+=" (midnight)";if(h===12)lbl+=" (noon)";opts[String(h)]=lbl;}return opts;}
var weekDayOptions={"0":"Sunday","1":"Monday","2":"Tuesday","3":"Wednesday","4":"Thursday","5":"Friday","6":"Saturday"};
var monthDayOptions={};for(var d=1;d<=28;d++){monthDayOptions[String(d)]=ordinal(d);}

var FIXED_SECTIONS=[
  {key:"discussions",label:"Discussions",    icon:"fas fa-comments",  iconBg:"#3b82f6",iconColor:"#fff"},
  {key:"members",    label:"New Members",    icon:"fas fa-user-plus", iconBg:"#10b981",iconColor:"#fff"},
  {key:"stats",      label:"Community Stats",icon:"fas fa-chart-bar", iconBg:"#6366f1",iconColor:"#fff"},
];
var INTEGRATION_SECTIONS={
  leaderboard:{key:"leaderboard",label:"Leaderboard",icon:"fas fa-trophy",      iconBg:"#3498db",iconColor:"#fff"},
  badges:     {key:"badges",     label:"Badges",     icon:"fas fa-award",       iconBg:"#8b5cf6",iconColor:"#fff"},
  pickem:     {key:"pickem",     label:"Pick'em",    icon:"fas fa-football-ball",iconBg:"#16a34a",iconColor:"#fff"},
  gamepedia:          {key:"gamepedia",         label:"Gamepedia",          icon:"fas fa-gamepad",     iconBg:"#e85d04",iconColor:"#fff"},
  resofireGamepedia:  {key:"resofireGamepedia", label:"Resofire Gamepedia", icon:"fas fa-gamepad",     iconBg:"#1a1a2e",iconColor:"#e94560"},
  favorites:          {key:"favorites",         label:"Favorites",          icon:"fas fa-heart",       iconBg:"#e11d48",iconColor:"#fff"},
  awards:     {key:"awards",     label:"Awards",     icon:"fas fa-star",        iconBg:"#f59e0b",iconColor:"#fff"},
};
var DEFAULT_ORDER=["discussions","members","stats","leaderboard","badges","pickem","gamepedia","resofireGamepedia","favorites","awards"];

var ExtIcon={view:function(vnode){var a=vnode.attrs;var sz=a.size||40;var fz=Math.round(sz*0.44);return m("div",{style:"width:"+sz+"px;height:"+sz+"px;border-radius:8px;background-color:"+(a.iconBg||"#6b7280")+";display:flex;align-items:center;justify-content:center;flex-shrink:0;"},m("i",{className:a.iconName||"fas fa-puzzle-piece",style:"color:"+(a.iconColor||"#fff")+";font-size:"+fz+"px;"}));}};

var IntegrationToggle={
  oninit:function(vnode){var key=vnode.attrs.settingKey;var ext=vnode.attrs.extData||{};var saved=app().data.settings[key];if(saved===undefined||saved===null){saved=ext.enabled?"1":"0";}vnode.state.on=saved==="1"||saved===true||saved===1;vnode.state.saving=false;},
  toggle:function(vnode){var ext=vnode.attrs.extData||{};if(!ext.enabled||vnode.state.saving)return;vnode.state.on=!vnode.state.on;vnode.state.saving=true;var newVal=vnode.state.on?"1":"0";saveSetting(vnode.attrs.settingKey,newVal).then(function(){vnode.state.saving=false;m.redraw();}).catch(function(){vnode.state.on=!vnode.state.on;vnode.state.saving=false;m.redraw();});},
  view:function(vnode){var a=vnode.attrs;var s=vnode.state;var ext=a.extData||{};var installed=!!ext.enabled;var on=installed&&s.on;var trackBg=!installed?"var(--control-bg)":on?"var(--primary-color,#4f46e5)":"var(--control-color,#d1d5db)";var thumbLeft=on?"22px":"2px";var cardOpacity=installed?"1":"0.55";var cursor=installed?"pointer":"not-allowed";var statusText=installed?(a.installedNote||"Extension active"):(a.notInstalledNote||"Not installed or disabled");var statusColor=installed?"#16a34a":"var(--muted-color)";return m("div",{style:"display:flex;align-items:center;gap:16px;padding:16px 20px;border-radius:8px;background:var(--control-bg);border:1px solid var(--control-bg);opacity:"+cardOpacity+";margin-bottom:10px;transition:opacity .2s;"},m(ExtIcon,{iconName:ext.iconName,iconColor:ext.iconColor,iconBg:ext.iconBg,size:44}),m("div",{style:"flex:1;min-width:0;"},m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:3px;"},m("span",{style:"font-size:16px;font-weight:700;color:var(--heading-color,var(--text-color));"},ext.title||a.settingKey),m("span",{style:"font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;background:"+(installed?"rgba(34,197,94,.15)":"var(--control-bg)")+";color:"+(installed?"#16a34a":"var(--muted-color)")+";"},installed?"Active":"Inactive")),m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.45;margin-bottom:4px;"},a.description),m("div",{style:"font-size:12px;color:"+statusColor+";"},statusText)),m("div",{style:"flex-shrink:0;cursor:"+cursor+";user-select:none;",title:installed?(on?"Disable in digest":"Enable in digest"):"Install and enable the extension first",onclick:function(){IntegrationToggle.toggle(vnode);}},m("div",{style:"position:relative;width:46px;height:26px;border-radius:13px;background-color:"+trackBg+";transition:background-color .2s;"},m("div",{style:"position:absolute;top:3px;left:"+thumbLeft+";width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .15s;"}))));} 
};

var FrequencyToggle={
  oninit:function(vnode){var saved=app().data.settings[vnode.attrs.settingKey];if(saved===undefined||saved===null)saved=vnode.attrs.defaultOn?"1":"0";vnode.state.on=saved==="1"||saved===true||saved===1;vnode.state.saving=false;},
  toggle:function(vnode){if(vnode.state.saving)return;vnode.state.on=!vnode.state.on;vnode.state.saving=true;var newVal=vnode.state.on?"1":"0";saveSetting(vnode.attrs.settingKey,newVal).then(function(){vnode.state.saving=false;m.redraw();}).catch(function(){vnode.state.on=!vnode.state.on;vnode.state.saving=false;m.redraw();});},
  view:function(vnode){var a=vnode.attrs;var s=vnode.state;var on=s.on;var trackBg=on?"var(--primary-color,#4f46e5)":"var(--control-color,#d1d5db)";var thumbLeft=on?"22px":"2px";return m("div",{style:"display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-radius:8px;background:var(--control-bg);border:1px solid var(--control-bg);margin-bottom:10px;"},m("div",{style:"display:flex;align-items:center;gap:14px;flex:1;"},m("div",{style:"width:44px;height:44px;border-radius:8px;flex-shrink:0;background:"+a.iconBg+";display:flex;align-items:center;justify-content:center;font-size:20px;"},a.emoji),m("div",null,m("div",{style:"font-size:15px;font-weight:700;color:var(--heading-color,var(--text-color));margin-bottom:2px;"},a.label),m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.4;"},a.description))),m("div",{style:"flex-shrink:0;cursor:pointer;margin-left:20px;",title:on?"Disable this frequency option":"Enable this frequency option",onclick:function(){FrequencyToggle.toggle(vnode);}},m("div",{style:"position:relative;width:46px;height:26px;border-radius:13px;background-color:"+trackBg+";transition:background-color .2s;"},m("div",{style:"position:absolute;top:3px;left:"+thumbLeft+";width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .15s;"}))));}
};

var NumberSetting={
  oninit:function(vnode){vnode.state.value=getSettingVal(vnode.attrs.settingKey,"");vnode.state.saving=false;vnode.state.saved=false;},
  save:function(vnode){if(vnode.state.saving)return;vnode.state.saving=true;saveSetting(vnode.attrs.settingKey,String(vnode.state.value)).then(function(){vnode.state.saving=false;vnode.state.saved=true;setTimeout(function(){vnode.state.saved=false;m.redraw();},5000);m.redraw();}).catch(function(){vnode.state.saving=false;m.redraw();});},
  view:function(vnode){var a=vnode.attrs;var s=vnode.state;return m("div",{className:"Form-group",style:"margin-bottom:20px;"},m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},a.label),a.help?m("p",{className:"helpText",style:"margin-bottom:6px;"},a.help):null,m("div",{style:"display:flex;align-items:center;gap:8px;"},m("input",{className:"FormControl",type:"number",min:a.min,max:a.max,step:a.step||1,value:s.value,style:"width:100px;",oninput:function(e){s.value=e.target.value;s.saved=false;},onblur:function(){NumberSetting.save(vnode);}}),s.saved?m("span",{style:"font-size:12px;color:#16a34a;"},"\u2713 Saved"):null));}
};

var ScheduleSection={
  oninit:function(vnode){
    var savedTz=getSettingVal("ernestdefoe-digest-mail.timezone","America/Chicago");
    var savedStart=getSettingVal("ernestdefoe-digest-mail.send_window_start",
                   getSettingVal("ernestdefoe-digest-mail.send_hour","8"));
    var savedEnd=getSettingVal("ernestdefoe-digest-mail.send_window_end",savedStart);
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
    var s=vnode.state;
    var tr=function(k){return app().translator.trans(k);};
    var hourOpts=buildHourOptions(s.tz);
    var tzOpts=TIMEZONES.map(function(z){
      return m("option",{value:z.tz,selected:s.tz===z.tz},tzOffsetLabel(z.tz)+" — "+z.label);
    });
    var isWindow=parseInt(s.windowEnd,10)>parseInt(s.windowStart,10);
    var windowSummary=isWindow
      ?"Digest emails will go out gradually from "+hourOpts[s.windowStart]+" to "+hourOpts[s.windowEnd]+". Subscribers are emailed in batches — your server stays responsive and no single minute carries the full load."
      :"All digest emails will begin sending at "+hourOpts[s.windowStart]+". Best for smaller forums with under 2,000 subscribers.";
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
          m("strong",{style:"color:var(--heading-color,var(--text-color));"},isWindow?"Spread send: ":"Single send: "),
          windowSummary
        )
      )
    );
  }
};

var SelectSetting={
  oninit:function(vnode){vnode.state.value=getSettingVal(vnode.attrs.settingKey,"");vnode.state.saving=false;},
  view:function(vnode){var a=vnode.attrs;var s=vnode.state;return m("div",{className:"Form-group",style:"margin-bottom:20px;"},m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},a.label),a.help?m("p",{className:"helpText",style:"margin-bottom:6px;"},a.help):null,m("select",{className:"FormControl Select-input",value:s.value,style:"max-width:260px;padding-bottom:8px;height:auto;line-height:1.4;",onchange:function(e){s.value=e.target.value;saveSetting(a.settingKey,e.target.value);}},Object.keys(a.options).map(function(k){return m("option",{value:k,selected:s.value===k},a.options[k]);})));}
};

var OnboardingSection={
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
    var s=vnode.state;
    var tr=function(k){return app().translator.trans(k);};
    var modeOptions={
      "none":        tr("ernestdefoe-digest-mail.admin.onboarding.mode_none"),
      "auto_enroll": tr("ernestdefoe-digest-mail.admin.onboarding.mode_auto_enroll"),
      "opt_in_modal":tr("ernestdefoe-digest-mail.admin.onboarding.mode_opt_in_modal"),
    };
    // Build frequency options from whichever frequencies are currently enabled.
    var freqOpts={};
    var freqLabels={"daily":"Daily","weekly":"Weekly","monthly":"Monthly"};
    ["daily","weekly","monthly"].forEach(function(f){
      var enabled=app().data.settings["ernestdefoe-digest-mail.allow_"+f];
      if(enabled==="1"||enabled===true||enabled===1)freqOpts[f]=freqLabels[f];
    });
    // If the currently saved frequency has since been disabled, fall back to
    // the first available option so the dropdown always shows a valid value.
    if(s.mode==="auto_enroll"&&freqOpts[s.frequency]===undefined){
      var firstAvailable=Object.keys(freqOpts)[0];
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
          ?m("p",{style:"font-size:13px;color:#dc2626;"},"No frequencies are currently enabled. Enable at least one frequency in User Frequency Options above.")
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

var TokenCheckerSection={
  oninit:function(vnode){
    vnode.state.token="";
    vnode.state.result=null;
    vnode.state.error=null;
    vnode.state.loading=false;
  },
  check:function(state){
    var token=state.token.trim();
    if(!token){state.error="Please enter a token.";state.result=null;m.redraw();return;}
    state.loading=true;state.result=null;state.error=null;m.redraw();
    app().request({
      method:"GET",
      url:app().forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/check-token?token="+encodeURIComponent(token)
    }).then(function(d){
      state.loading=false;state.result=d;m.redraw();
    }).catch(function(e){
      state.loading=false;
      state.error=(e&&e.response&&e.response.errors&&e.response.errors[0]&&e.response.errors[0].detail)||(e&&e.message)||"Failed to check token.";
      m.redraw();
    });
  },
  view:function(vnode){
    var s=vnode.state;
    var formatDate=function(str){
      if(!str)return "—";
      var d=new Date(str.replace(" ","T")+"Z");
      return d.toLocaleDateString(undefined,{month:"short",day:"numeric",year:"numeric"})+" at "+d.toLocaleTimeString(undefined,{hour:"2-digit",minute:"2-digit"});
    };
    return m("div",{className:"ExtensionPage-settings"},
      m("div",{style:"max-width:600px;margin:0 auto;"},
        m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},"Token Checker"),
        m("p",{className:"helpText",style:"margin-bottom:12px;"},"Paste an unsubscribe token to verify it is valid. Use this to confirm a user's token is intact without sending an email."),
        m("div",{className:"Form-group"},
          m("div",{style:"display:flex;gap:8px;align-items:center;"},
            m("input",{
              className:"FormControl",
              type:"text",
              placeholder:"Paste token here…",
              value:s.token,
              style:"flex:1;font-family:monospace;font-size:12px;",
              oninput:function(e){s.token=e.target.value;s.result=null;s.error=null;},
              onkeydown:function(e){if(e.key==="Enter"){e.preventDefault();TokenCheckerSection.check(s);}}
            }),
            m("button",{
              className:"Button Button--primary",
              disabled:s.loading,
              onclick:function(e){e.preventDefault();TokenCheckerSection.check(s);}
            },s.loading?"Checking…":"Check Token")
          )
        ),
        s.result?m("div",{className:"Alert Alert--success",style:"margin-top:12px;"},
          m("div",{style:"font-weight:700;margin-bottom:4px;"},"\u2713 Valid token"),
          m("div",{style:"font-size:13px;"},
            m("span",{style:"color:var(--muted-color);"},"User: "),
            m("strong",null,s.result.username),
            m("span",{style:"color:var(--muted-color);margin-left:16px;"},"Created: "),
            m("strong",null,formatDate(s.result.created_at)),
            m("span",{style:"color:var(--muted-color);margin-left:16px;"},"Expires: "),
            m("strong",null,formatDate(s.result.expires_at))
          )
        ):null,
        s.error?m("div",{className:"Alert Alert--error",style:"margin-top:12px;"},s.error):null
      )
    );
  }
};

var TestSendSection={
  oninit:function(vnode){vnode.state.email="";vnode.state.frequency="weekly";vnode.state.theme="light";vnode.state.loading=false;vnode.state.result=null;vnode.state.error=null;},
  send:function(state){var email=state.email.trim();if(!email){state.error=app().translator.trans("ernestdefoe-digest-mail.admin.test_send.error_empty_email");state.result=null;m.redraw();return;}state.loading=true;state.result=null;state.error=null;m.redraw();app().request({method:"POST",url:app().forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/test-send",body:{email:email,frequency:state.frequency,theme:state.theme}}).then(function(data){state.loading=false;state.result=data;m.redraw();}).catch(function(e){state.loading=false;var serverMsg=(e&&e.response&&e.response.error)||(e&&e.message)||null;state.error=serverMsg||app().translator.trans("ernestdefoe-digest-mail.admin.test_send.error_generic");m.redraw();});},
  view:function(vnode){
    var state=vnode.state;var themePickerEnabled=true;var tr=function(k,v){return app().translator.trans(k,v);};
    var themeToggle=themePickerEnabled
      ?m("div",{style:"display:flex;align-items:center;gap:10px;margin-bottom:12px;"},m("label",{style:"font-size:13px;color:var(--muted-color);white-space:nowrap;"},tr("ernestdefoe-digest-mail.admin.test_send.theme_label")+":"),m("div",{style:"display:flex;gap:0;border:1px solid var(--control-bg);border-radius:6px;overflow:hidden;"},m("button",{style:"padding:6px 14px;font-size:13px;font-weight:500;border:none;cursor:pointer;"+(state.theme==="light"?"background:var(--body-bg,#fff);color:var(--text-color,#111827);box-shadow:inset 0 0 0 1px var(--control-bg);":"background:var(--control-bg);color:var(--muted-color);"),onclick:function(e){e.preventDefault();state.theme="light";m.redraw();}},m("span",{style:"margin-right:5px;"},"☀️"),tr("ernestdefoe-digest-mail.admin.test_send.theme_light")),m("button",{style:"padding:6px 14px;font-size:13px;font-weight:500;border:none;cursor:pointer;border-left:1px solid var(--control-bg);"+(state.theme==="dark"?"background:var(--header-bg,#1f2937);color:var(--header-color,#e5e7eb);":"background:var(--control-bg);color:var(--muted-color);"),onclick:function(e){e.preventDefault();state.theme="dark";m.redraw();}},m("span",{style:"margin-right:5px;"},"🌙"),tr("ernestdefoe-digest-mail.admin.test_send.theme_dark"))),m("span",{style:"font-size:12px;color:var(--muted-color);"},state.theme==="light"?tr("ernestdefoe-digest-mail.admin.test_send.theme_hint_light"):tr("ernestdefoe-digest-mail.admin.test_send.theme_hint_dark")))
      :m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:8px 12px;background:var(--control-bg);border-radius:6px;"},m("span",{style:"font-size:13px;color:var(--muted-color);"},"☀️ "+tr("ernestdefoe-digest-mail.admin.test_send.theme_light_only")));
    return m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},tr("ernestdefoe-digest-mail.admin.test_send.heading")),m("p",{className:"helpText"},tr("ernestdefoe-digest-mail.admin.test_send.help")),m("div",{className:"Form-group",style:"margin-top:1rem;"},m("input",{className:"FormControl",type:"email",placeholder:tr("ernestdefoe-digest-mail.admin.test_send.email_placeholder"),value:state.email,disabled:state.loading,oninput:function(e){state.email=e.target.value;state.result=null;state.error=null;},style:"margin-bottom:8px;"}),m("div",{style:"display:flex;align-items:center;gap:10px;margin-bottom:12px;"},m("label",{style:"font-size:13px;color:var(--muted-color);white-space:nowrap;"},tr("ernestdefoe-digest-mail.admin.test_send.frequency_label")+":"),m("select",{className:"FormControl",value:state.frequency,disabled:state.loading,style:"padding-top:6px;padding-bottom:8px;height:auto;line-height:1.5;",onchange:function(e){state.frequency=e.target.value;}},m("option",{value:"daily"},"Daily"),m("option",{value:"weekly"},"Weekly"),m("option",{value:"monthly"},"Monthly"))),themeToggle,m("button",{className:"Button Button--primary",disabled:state.loading,onclick:function(e){e.preventDefault();TestSendSection.send(state);}},state.loading?tr("ernestdefoe-digest-mail.admin.test_send.sending_button"):tr("ernestdefoe-digest-mail.admin.test_send.send_button"))),state.result?m("div",{className:"Alert Alert--success",style:"margin-top:1rem;"},tr("ernestdefoe-digest-mail.admin.test_send.success",{email:state.result.to,frequency:state.result.frequency})):null,state.error?m("div",{className:"Alert Alert--error",style:"margin-top:1rem;"},state.error):null));
  }
};

var SettingsTab={
  view:function(){
    var exts=(app().forum.attribute("digestExtensions"))||{};
    var tr=function(k){return app().translator.trans(k);};
    var sh=function(t){return m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},t);};
    return m("div",null,
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh("Content Limits"),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.featured_discussion_id",min:1,label:tr("ernestdefoe-digest-mail.admin.settings.featured_discussion_label"),help:tr("ernestdefoe-digest-mail.admin.settings.featured_discussion_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_new",         min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_new_label"),         help:tr("ernestdefoe-digest-mail.admin.settings.limit_new_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_hot",         min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_hot_label"),         help:tr("ernestdefoe-digest-mail.admin.settings.limit_hot_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_unread",      min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_unread_label"),      help:tr("ernestdefoe-digest-mail.admin.settings.limit_unread_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_members",     min:1,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_members_label"),     help:tr("ernestdefoe-digest-mail.admin.settings.limit_members_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_leaderboard", min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_leaderboard_label"), help:tr("ernestdefoe-digest-mail.admin.settings.limit_leaderboard_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_badges",      min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_badges_label"),      help:tr("ernestdefoe-digest-mail.admin.settings.limit_badges_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_pickem",      min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_pickem_label"),      help:tr("ernestdefoe-digest-mail.admin.settings.limit_pickem_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_gamepedia",   min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_gamepedia_label"),   help:tr("ernestdefoe-digest-mail.admin.settings.limit_gamepedia_help")}),
        !!(exts.resofireGamepedia||{}).enabled?m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_resofire_gamepedia",min:3,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_resofire_gamepedia_label"),help:tr("ernestdefoe-digest-mail.admin.settings.limit_resofire_gamepedia_help")}):null,
        (!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled)?m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.limit_favorites",min:0,max:20,label:tr("ernestdefoe-digest-mail.admin.settings.limit_favorites_label"),help:tr("ernestdefoe-digest-mail.admin.settings.limit_favorites_help")}):null,
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.hot_reply_weight",  min:0,max:10,step:0.1,label:tr("ernestdefoe-digest-mail.admin.settings.hot_reply_weight_label"),  help:tr("ernestdefoe-digest-mail.admin.settings.hot_reply_weight_help")}),
        m(NumberSetting,{settingKey:"ernestdefoe-digest-mail.hot_recency_weight",min:0,max:10,step:0.1,label:tr("ernestdefoe-digest-mail.admin.settings.hot_recency_weight_label"),help:tr("ernestdefoe-digest-mail.admin.settings.hot_recency_weight_help")})
      )),
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh("Schedule"),
        m(ScheduleSection),
        m(SelectSetting,{settingKey:"ernestdefoe-digest-mail.weekly_day",  options:weekDayOptions,  label:tr("ernestdefoe-digest-mail.admin.settings.weekly_day_label"),  help:tr("ernestdefoe-digest-mail.admin.settings.weekly_day_help")}),
        m(SelectSetting,{settingKey:"ernestdefoe-digest-mail.monthly_day", options:monthDayOptions, label:tr("ernestdefoe-digest-mail.admin.settings.monthly_day_label"), help:tr("ernestdefoe-digest-mail.admin.settings.monthly_day_help")})
      )),

      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh("User Frequency Options"),
        m("p",{className:"helpText",style:"margin-bottom:12px;"},"Choose which digest frequency options are available to users on their settings page. Disabled options are hidden from the frequency selector."),
        m(FrequencyToggle,{settingKey:"ernestdefoe-digest-mail.allow_daily",  defaultOn:false,emoji:"📅",iconBg:"#fef3c7",label:"Daily",  description:"Users can opt in to receive a digest every day. Best for high-traffic forums. Off by default."}),
        m(FrequencyToggle,{settingKey:"ernestdefoe-digest-mail.allow_weekly", defaultOn:true, emoji:"📆",iconBg:"#ede9fe",label:"Weekly", description:"Users can opt in to receive a weekly digest. Recommended for most forums."}),
        m(FrequencyToggle,{settingKey:"ernestdefoe-digest-mail.allow_monthly",defaultOn:true, emoji:"🗓️",iconBg:"#dbeafe",label:"Monthly",description:"Users can opt in to receive a monthly digest. Good for low-traffic or announcement-focused forums."})
      )),
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh(app().translator.trans("ernestdefoe-digest-mail.admin.onboarding.section_heading")),
        m(OnboardingSection)
      )),
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh("Extension Integrations"),
        m("p",{className:"helpText",style:"margin-bottom:12px;"},"Control which optional extension integrations are included in digest emails. A toggle is only activatable when the required extension is installed and enabled. Enabled integrations appear in the Digest Order tab."),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_leaderboard",extData:exts.leaderboard||{},description:"Show the top members leaderboard in each digest, including rank changes, points earned during the period, and a biggest-mover callout.",installedNote:"huseyinfiliz/leaderboard is installed and active",notInstalledNote:"huseyinfiliz/leaderboard is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_badges",     extData:exts.badges||{},     description:"Show badges earned during the period, the most-awarded badge, and the rarest badge awarded.",                                          installedNote:"fof/badges is installed and active",              notInstalledNote:"fof/badges is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_pickem",     extData:exts.pickem||{},     description:"Show upcoming pick'em matches, recent results, and the pick'em leaderboard.",                                                          installedNote:"huseyinfiliz/pickem is installed and active",     notInstalledNote:"huseyinfiliz/pickem is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_gamepedia",  extData:exts.gamepedia||{},         description:"Show the most discussed game pages and newly added games from Gamepedia.",                                                              installedNote:"huseyinfiliz/gamepedia is installed and active",        notInstalledNote:"huseyinfiliz/gamepedia is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_resofire_gamepedia",extData:exts.resofireGamepedia||{},description:"Show the most discussed games, newly added games, and top genres from Resofire Gamepedia.",                                 installedNote:"resofire/gamepedia is installed and active",            notInstalledNote:"resofire/gamepedia is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_reactions",  extData:exts.reactions||{},  description:"Use fof/reactions data for the Favorite Discussions section. When enabled, shows a per-reaction emoji breakdown instead of a plain like count. Thumbsdown and Confused reactions are excluded from scoring.",installedNote:"fof/reactions is installed and active",notInstalledNote:"fof/reactions is not installed or is disabled"}),
        m(IntegrationToggle,{settingKey:"ernestdefoe-digest-mail.enable_awards",     extData:exts.awards||{},     description:"Show active and upcoming awards in the digest — including banner image, voting deadline countdown, category list, vote totals, and current front-runners when live votes are enabled.",installedNote:"huseyinfiliz/awards is installed and active",notInstalledNote:"huseyinfiliz/awards is not installed or is disabled"}),
        m("div",{style:"margin-top:24px;padding-top:20px;border-top:1px solid var(--control-bg);margin-bottom:8px;"},
          m("p",{style:"font-size:12px;color:var(--muted-color);margin:0;"},
            "The following section is enabled automatically based on your active extensions and cannot be toggled here. To disable it, turn off ",
            m("strong",null,"flarum/likes"),
            " or set its limit to 0 in Content Limits above."
          )
        ),
        m("div",{style:"display:flex;align-items:center;gap:16px;padding:16px 20px;border-radius:8px;background:var(--control-bg);border:1px solid var(--control-bg);margin-bottom:10px;opacity:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"1":"0.55")+";"},
          m(ExtIcon,{iconName:"fas fa-heart",iconColor:"#fff",iconBg:"#e11d48",size:44}),
          m("div",{style:"flex:1;min-width:0;"},
            m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:3px;"},
              m("span",{style:"font-size:16px;font-weight:700;color:var(--heading-color,var(--text-color));"},"Favorite Discussions"),
              m("span",{style:"font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;background:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"rgba(34,197,94,.15)":"var(--control-bg)")+";color:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"#16a34a":"var(--muted-color);")+";"},!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"Active":"Inactive")),
            m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.45;margin-bottom:4px;"},"Requires flarum/likes. Shows the most-liked discussions from the digest period. If fof/reactions is also enabled above, shows a per-reaction emoji breakdown instead. To remove Favorites from the digest entirely, disable flarum/likes or set the limit to 0."),
            m("div",{style:"font-size:12px;color:"+(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled?"#16a34a":"var(--muted-color);")+";"},
              !!(exts.reactions||{}).enabled&&!!(exts.likes||{}).enabled?"flarum/likes + fof/reactions active — showing reaction breakdown":!!(exts.likes||{}).enabled?"flarum/likes is active — showing like counts":"flarum/likes is disabled — section will not appear in digest"))
        )
      )),
      m(TokenCheckerSection),
      m(TestSendSection)
    );
  }
};

var DigestOrderTab={
  oninit:function(vnode){
    var saved=getSettingVal("ernestdefoe-digest-mail.section_order","");
    var order=[];try{order=saved?JSON.parse(saved):[];}catch(e){order=[];}
    if(!order.length)order=DEFAULT_ORDER.slice();
    vnode.state.order=order;vnode.state.saving=false;vnode.state.saved=false;
  },
  activeSections:function(order){
    var exts=(app().forum.attribute("digestExtensions"))||{};
    var integrationEnabled={
      leaderboard:       getSettingVal("ernestdefoe-digest-mail.enable_leaderboard","1")==="1"&&!!(exts.leaderboard||{}).enabled,
      badges:            getSettingVal("ernestdefoe-digest-mail.enable_badges","1")==="1"     &&!!(exts.badges||{}).enabled,
      pickem:            getSettingVal("ernestdefoe-digest-mail.enable_pickem","1")==="1"     &&!!(exts.pickem||{}).enabled,
      gamepedia:         getSettingVal("ernestdefoe-digest-mail.enable_gamepedia","1")==="1"  &&!!(exts.gamepedia||{}).enabled,
      resofireGamepedia: getSettingVal("ernestdefoe-digest-mail.enable_resofire_gamepedia","1")==="1"&&!!(exts.resofireGamepedia||{}).enabled,
      favorites:         (parseInt(getSettingVal("ernestdefoe-digest-mail.limit_favorites","6"),10)>0)&&(!!(exts.likes||{}).enabled||!!(exts.reactions||{}).enabled),
      awards:            getSettingVal("ernestdefoe-digest-mail.enable_awards","1")==="1"     &&!!(exts.awards||{}).enabled,
    };
    var allSections={};
    FIXED_SECTIONS.forEach(function(s){allSections[s.key]=s;});
    Object.keys(INTEGRATION_SECTIONS).forEach(function(k){allSections[k]=INTEGRATION_SECTIONS[k];});
    var active=order.filter(function(key){
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
    var order=vnode.state.order.slice();
    var sections=DigestOrderTab.activeSections(order);
    var newIdx=index+direction;
    if(newIdx<0||newIdx>=sections.length)return;
    var keyA=sections[index].key;var keyB=sections[newIdx].key;
    var iA=order.indexOf(keyA);var iB=order.indexOf(keyB);
    if(iA===-1){order.push(keyA);iA=order.length-1;}
    if(iB===-1){order.push(keyB);iB=order.length-1;}
    var tmp=order[iA];order[iA]=order[iB];order[iB]=tmp;
    vnode.state.order=order;vnode.state.saved=false;vnode.state.saving=true;
    saveSetting("ernestdefoe-digest-mail.section_order",JSON.stringify(order)).then(function(){
      vnode.state.saving=false;vnode.state.saved=true;
      setTimeout(function(){vnode.state.saved=false;m.redraw();},5000);
      m.redraw();
    });
  },
  view:function(vnode){
    var s=vnode.state;
    var sections=DigestOrderTab.activeSections(s.order);
    var isFixed=function(key){return FIXED_SECTIONS.some(function(f){return f.key===key;});};
    return m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
      m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},"Digest Section Order"),
      m("p",{className:"helpText",style:"margin-bottom:16px;"},"Use the arrows to set the order sections appear in the digest email. Only enabled integration sections appear here — enable them in the Settings tab first."),
      sections.length===0
        ?m("p",{style:"color:var(--muted-color);font-size:14px;"},"No sections active. Enable integrations in the Settings tab.")
        :sections.map(function(section,index){
          var fixed=isFixed(section.key);
          var isFirst=index===0;var isLast=index===sections.length-1;
          var btnBase="width:30px;height:28px;border:1px solid var(--control-bg);border-radius:5px;background:var(--body-bg);font-size:14px;display:flex;align-items:center;justify-content:center;";
          return m("div",{key:section.key,style:"display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:8px;margin-bottom:8px;background:var(--control-bg);border:1px solid var(--control-bg);"},
            m("div",{style:"font-size:18px;font-weight:700;color:var(--muted-color);width:24px;text-align:center;flex-shrink:0;"},index+1),
            m(ExtIcon,{iconName:section.icon,iconColor:section.iconColor,iconBg:section.iconBg,size:40}),
            m("div",{style:"flex:1;min-width:0;"},
              m("div",{style:"display:flex;align-items:center;gap:8px;"},
                m("span",{style:"font-size:15px;font-weight:700;color:var(--heading-color,var(--text-color));"},section.label),
                fixed?m("span",{style:"font-size:10px;font-weight:600;padding:2px 7px;border-radius:20px;background:var(--control-bg);color:var(--muted-color);border:1px solid var(--muted-color);"},"Always shown"):null
              )
            ),
            m("div",{style:"display:flex;flex-direction:column;gap:2px;flex-shrink:0;"},
              m("button",{style:btnBase+"cursor:"+(isFirst?"not-allowed":"pointer")+";color:"+(isFirst?"var(--muted-color)":"var(--text-color)"),disabled:isFirst,title:"Move up",onclick:function(e){e.preventDefault();DigestOrderTab.move(vnode,index,-1);}},"\u25B2"),
              m("button",{style:btnBase+"cursor:"+(isLast?"not-allowed":"pointer")+";color:"+(isLast?"var(--muted-color)":"var(--text-color)"),disabled:isLast,title:"Move down",onclick:function(e){e.preventDefault();DigestOrderTab.move(vnode,index,1);}},"\u25BC")
            )
          );
        }),
      s.saving?m("p",{style:"font-size:12px;color:var(--muted-color);margin-top:8px;"},"Saving\u2026"):null,
      s.saved ?m("p",{style:"font-size:12px;color:#16a34a;margin-top:8px;"},"\u2713 Order saved"):null
    ));
  }
};

var SubscriberList={
  oninit:function(vnode){
    vnode.state.loading=false;
    vnode.state.error=null;
    vnode.state.data=null;
    vnode.state.page=1;
  },
  load:function(vnode,page){
    var freq=vnode.attrs.frequency;
    vnode.state.loading=true;
    vnode.state.error=null;
    vnode.state.page=page;
    m.redraw();
    app().request({
      method:"GET",
      url:app().forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/subscribers?frequency="+freq+"&page="+page+"&per_page=15"
    }).then(function(d){
      vnode.state.loading=false;
      vnode.state.data=d;
      m.redraw();
    }).catch(function(e){
      vnode.state.loading=false;
      vnode.state.error=(e&&e.message)||"Failed to load subscribers.";
      m.redraw();
    });
  },
  view:function(vnode){
    var s=vnode.state;
    var color=vnode.attrs.color||"#f59e0b";
    var formatDate=function(str){
      if(!str)return "Never";
      var d=new Date(str.replace(" ","T")+"Z");
      return d.toLocaleDateString(undefined,{month:"short",day:"numeric",year:"numeric"});
    };
    var renderAvatar=function(user){
      if(user.avatar_url){
        return m("img",{src:user.avatar_url,style:"width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;"});
      }
      var initials=(user.username||"?").charAt(0).toUpperCase();
      return m("div",{style:"width:28px;height:28px;border-radius:50%;background:"+color+";display:flex;align-items:center;justify-content:center;flex-shrink:0;"},
        m("span",{style:"font-size:12px;font-weight:700;color:#fff;"},initials)
      );
    };

    if(s.loading){
      return m("div",{style:"padding:16px;text-align:center;color:var(--muted-color);font-size:13px;"},"Loading\u2026");
    }
    if(s.error){
      return m("div",{style:"padding:12px;color:#dc2626;font-size:13px;"},s.error);
    }
    if(!s.data){
      return m("div",{style:"padding:16px;text-align:center;color:var(--muted-color);font-size:13px;"},"No data loaded.");
    }

    var d=s.data;
    var hasPrev=d.page>1;
    var hasNext=d.page<d.total_pages;

    return m("div",{style:"padding:12px 0 4px;"},
      d.data.length===0
        ?m("p",{style:"color:var(--muted-color);font-size:13px;padding:0 18px;margin:0;"},"No subscribers found.")
        :m("div",null,
          d.data.map(function(user){
            return m("div",{key:user.id,style:"display:flex;align-items:center;gap:10px;padding:8px 18px;border-bottom:1px solid var(--control-bg);"},
              renderAvatar(user),
              m("div",{style:"flex:1;min-width:0;"},
                m("span",{style:"font-size:13px;font-weight:600;color:var(--heading-color,var(--text-color));display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"},user.username)
              ),
              m("span",{style:"font-size:11px;color:var(--muted-color);white-space:nowrap;flex-shrink:0;"},
                "Last sent: "+formatDate(user.last_sent)
              )
            );
          }),
          (hasPrev||hasNext)?m("div",{style:"display:flex;align-items:center;justify-content:space-between;padding:10px 18px 6px;"},
            m("button",{
              style:"font-size:12px;padding:4px 10px;border:1px solid var(--control-bg);border-radius:5px;background:var(--body-bg);color:"+(hasPrev?"var(--text-color)":"var(--muted-color)")+";cursor:"+(hasPrev?"pointer":"not-allowed")+";",
              disabled:!hasPrev,
              onclick:function(e){e.preventDefault();if(hasPrev)SubscriberList.load(vnode,d.page-1);}
            },"\u2190 Prev"),
            m("span",{style:"font-size:12px;color:var(--muted-color);"},"Page "+d.page+" of "+d.total_pages+" \u2014 "+d.total+" total"),
            m("button",{
              style:"font-size:12px;padding:4px 10px;border:1px solid var(--control-bg);border-radius:5px;background:var(--body-bg);color:"+(hasNext?"var(--text-color)":"var(--muted-color)")+";cursor:"+(hasNext?"pointer":"not-allowed")+";",
              disabled:!hasNext,
              onclick:function(e){e.preventDefault();if(hasNext)SubscriberList.load(vnode,d.page+1);}
            },"Next \u2192")
          ):null
        )
    );
  }
};

var StatsTab={
  oninit:function(vnode){
    vnode.state.loading=true;
    vnode.state.error=null;
    vnode.state.data=null;
    app().request({method:"GET",url:app().forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/stats"})
      .then(function(d){vnode.state.loading=false;vnode.state.data=d;m.redraw();})
      .catch(function(e){vnode.state.loading=false;vnode.state.error=(e&&e.message)||"Failed to load statistics.";m.redraw();});
  },
  view:function(vnode){
    var s=vnode.state;
    var sh=function(t){return m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},t);};
    var card=function(label,value,sub){return m("div",{style:"background:var(--control-bg);border-radius:10px;padding:20px 24px;text-align:center;"},m("div",{style:"font-size:28px;font-weight:800;color:var(--heading-color,var(--text-color));line-height:1;margin-bottom:6px;"},value),m("div",{style:"font-size:13px;font-weight:600;color:var(--muted-color);margin-bottom:sub?4px:0;"},label),sub?m("div",{style:"font-size:12px;color:var(--muted-color);"},sub):null);};
    var freqColor={"daily":"#f59e0b","weekly":"#3b82f6","monthly":"#8b5cf6"};
    var freqLabel={"daily":"Daily","weekly":"Weekly","monthly":"Monthly"};

    if(s.loading) return m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;padding:40px 0;text-align:center;color:var(--muted-color);"},"Loading statistics…"));
    if(s.error)   return m("div",{className:"ExtensionPage-settings"},m("div",{className:"Alert Alert--error",style:"max-width:600px;margin:0 auto;"},s.error));

    var sub=s.data.subscriptions;
    var lastSent=s.data.last_sent;
    var log=s.data.send_log||[];

    var formatDate=function(str){
      if(!str)return "—";
      var d=new Date(str.replace(" ","T")+"Z");
      return d.toLocaleDateString(undefined,{month:"short",day:"numeric",year:"numeric"})+" at "+d.toLocaleTimeString(undefined,{hour:"2-digit",minute:"2-digit"});
    };

    return m("div",null,
      // Subscription overview cards
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh("Subscription Overview"),
        m("div",{style:"display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;"},
          card("Total Members",   sub.total_members),
          card("Subscribers",     sub.total_subscribed),
          card("Subscription Rate", sub.subscription_rate+"%", "of confirmed members")
        )
      )),

      // By frequency breakdown
      m("div",{className:"ExtensionPage-settings"},m("div",{style:"max-width:600px;margin:0 auto;"},
        sh("Subscribers by Frequency"),
        m("div",{style:"display:flex;flex-direction:column;gap:10px;"},
          ["daily","weekly","monthly"].map(function(freq){
            var count=sub.by_frequency[freq]||0;
            var pct=sub.total_subscribed>0?Math.round(count/sub.total_subscribed*100):0;
            var color=freqColor[freq];
            var panelKey="panel_"+freq;
            var open=s[panelKey]||false;
            var listKey="list_"+freq;
            return m("div",{key:freq,style:"background:var(--control-bg);border-radius:8px;overflow:hidden;"},
              m("div",{style:"padding:14px 18px;"},
                m("div",{style:"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;"},
                  m("span",{style:"font-size:14px;font-weight:700;color:var(--heading-color,var(--text-color));"},freqLabel[freq]),
                  m("div",{style:"display:flex;align-items:center;gap:10px;"},
                    m("span",{style:"font-size:14px;font-weight:700;color:"+color+";"},count+" user"+(count!==1?"s":"")),
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
                    },open?"Hide \u25b2":"View \u25bc"):null
                  )
                ),
                m("div",{style:"height:6px;border-radius:3px;background:var(--body-bg);overflow:hidden;"},
                  m("div",{style:"height:100%;border-radius:3px;background:"+color+";width:"+pct+"%;transition:width .4s;"})
                ),
                m("div",{style:"font-size:11px;color:var(--muted-color);margin-top:5px;text-align:right;"},pct+"% of subscribers")
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
        sh("Last Sent"),
        m("div",{style:"display:flex;flex-direction:column;gap:8px;"},
          ["daily","weekly","monthly"].map(function(freq){
            var color=freqColor[freq];
            var val=formatDate(lastSent[freq]);
            var hasValue=!!lastSent[freq];
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
        sh("Send History"),
        log.length===0
          ?m("p",{style:"color:var(--muted-color);font-size:14px;"},"No send history yet. History is recorded from this version onwards.")
          :m("div",{style:"overflow:hidden;border-radius:8px;border:1px solid var(--control-bg);"},
              m("table",{style:"width:100%;border-collapse:collapse;font-size:13px;"},
                m("thead",null,
                  m("tr",{style:"background:var(--control-bg);"},
                    m("th",{style:"padding:10px 14px;text-align:left;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},"Frequency"),
                    m("th",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},"Sent"),
                    m("th",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},"Skipped"),
                    m("th",{style:"padding:10px 14px;text-align:right;font-weight:700;color:var(--muted-color);font-size:11px;text-transform:uppercase;letter-spacing:.5px;"},"Date & Time")
                  )
                ),
                m("tbody",null,
                  log.map(function(row,i){
                    var color=freqColor[row.frequency]||"var(--muted-color)";
                    var bg=i%2===0?"var(--body-bg)":"var(--control-bg)";
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


var ServerTab={
  oninit:function(vnode){
    vnode.state.queueName=getSettingVal("ernestdefoe-digest-mail.queue_name","digest");
    vnode.state.basePath=null;
    vnode.state.basePathLoaded=false;
    vnode.state.queueType="database";
    vnode.state.redisSubMode="horizon";
    app().request({method:"GET",url:app().forum.attribute("apiUrl")+"/ernestdefoe/digest-mail/stats"})
      .then(function(d){
        vnode.state.basePath=(d&&d.base_path&&d.base_path.length)?d.base_path:null;
        vnode.state.basePathLoaded=true;
        m.redraw();
      })
      .catch(function(){vnode.state.basePathLoaded=true;m.redraw();});
  },
  view:function(vnode){
    var s=vnode.state;
    var tr=function(k){return app().translator.trans(k);};
    var sh=function(t){return m("h3",{style:"font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted-color);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--control-bg);"},t);};
    var qn=getSettingVal("ernestdefoe-digest-mail.queue_name","digest");
    var tries=getSettingVal("ernestdefoe-digest-mail.queue_tries","3");
    var bp=s.basePath||"/path/to/flarum";
    var notice=function(icon,title,body,color){
      return m("div",{style:"display:flex;gap:14px;padding:16px 18px;border-radius:8px;background:var(--control-bg);border-left:4px solid "+(color||"var(--primary-color,#4f46e5)")+";margin-bottom:14px;"},
        m("div",{style:"font-size:22px;flex-shrink:0;line-height:1.3;"},icon),
        m("div",{style:"flex:1;min-width:0;"},
          m("div",{style:"font-size:13px;font-weight:700;color:var(--heading-color,var(--text-color));margin-bottom:4px;"},title),
          m("div",{style:"font-size:13px;color:var(--muted-color);line-height:1.6;"},body)
        )
      );
    };
    var code=function(t){return m("code",{style:"background:var(--body-bg);padding:2px 6px;border-radius:4px;font-size:12px;font-family:monospace;word-break:break-all;"},t);};
    var cronBlock=function(label,text){
      return m("div",{style:"margin-bottom:16px;"},
        m("div",{style:"font-size:13px;font-weight:600;color:var(--heading-color,var(--text-color));margin-bottom:6px;"},label),
        m("div",{style:"position:relative;"},
          m("pre",{style:"background:var(--body-bg);border:1px solid var(--control-bg);border-radius:6px;padding:10px 44px 10px 14px;font-family:monospace;font-size:12px;color:var(--text-color);word-break:break-all;line-height:1.7;white-space:pre-wrap;margin:0;"},text),
          m("button",{
            title:"Copy to clipboard",
            style:"position:absolute;top:8px;right:8px;background:var(--control-bg);border:1px solid var(--control-bg);border-radius:4px;cursor:pointer;padding:4px 6px;font-size:13px;color:var(--muted-color);line-height:1;",
            onclick:function(e){
              navigator.clipboard&&navigator.clipboard.writeText(text).then(function(){
                var btn=e.target.closest("button")||e.target;
                var prev=btn.textContent;
                btn.textContent="\u2713";
                btn.style.color="var(--success-color,#10b981)";
                setTimeout(function(){btn.textContent=prev;btn.style.color="var(--muted-color)";},1500);
              });
            }
          },"\uD83D\uDCCB")
        )
      );
    };
    var tbl_head=["Forum Size","Chunk Size","Workers","Tries","Send Mode","Cron Strategy"];
    var tbl_rows=[
      ["100\u2013500 members",   "200",   "1", "2", "Single hour",   "One worker, single-hour mode \u2014 all subscribers dispatched in one run"],
      ["500\u20132,000",         "500",   "1", "3", "Single hour",   "One worker, single-hour or short window"],
      ["2,000\u20135,000",       "1000",  "2", "3", "1\u20132 hr window", "Two workers, 1\u20132 hour window, ~1,000 users/min"],
      ["5,000\u201315,000",      "2000",  "3", "3", "2\u20133 hr window", "Three workers, 2\u20133 hour window, ~2,000 users/min"],
      ["15,000\u201350,000",     "5000",  "5", "3", "2\u20134 hr window", "Five workers, 2\u20134 hour window, ~5,000 users/min"],
      ["50,000\u2013100,000",    "7500",  "8", "3", "3\u20134 hr window", "Eight workers, 3\u20134 hour window, ~7,500 users/min"],
      ["100,000+",          "10000", "10","3", "4+ hr window",  "Ten+ workers, 4+ hour window, consider Redis + Supervisor"],
    ];

    // ---- cron line strings (live values) ------------------------------------
    var lineScheduler = "* * * * * cd "+bp+" && php flarum schedule:run >> /dev/null 2>&1";
    var lineWorker    = "* * * * * cd "+bp+" && php flarum queue:work --queue="+qn+",default --max-time=55 --tries="+tries+" --backoff=30 >> /dev/null 2>&1";
    var lineWorkers3  = "# Add one line per worker \u2014 e.g. 3 workers:\n"+lineWorker+"\n"+lineWorker+"\n"+lineWorker;
    var lineEnqueue   = "# Optional: pre-build jobs before the window opens (large forums only):\n50 12 * * * cd "+bp+" && php flarum digest:enqueue --frequency=daily --delay=600 >> /dev/null 2>&1";
    var supervisorConf= "[program:flarum-worker]\ncommand=php "+bp+"/flarum queue:work --queue="+qn+",default --tries="+tries+" --backoff=30\ndirectory="+bp+"\nautostart=true\nautorestart=true\nnumprocs=2\nstopwaitsecs=60\nuser=www-data\nredirect_stderr=true\nstdout_logfile="+bp+"/storage/logs/worker.log";
    var horizonConf= "[program:horizon]\nprocess_name=%(program_name)s\ncommand=php "+bp+"/flarum horizon\nautostart=true\nautorestart=true\nuser=www-data\nredirect_stderr=true\nstdout_logfile="+bp+"/storage/logs/horizon.log\nstopwaitsecs=3600";

    // ---- queue type toggle --------------------------------------------------
    var toggleBtn=function(label,val){
      var active=s.queueType===val;
      return m("button",{
        style:"padding:6px 18px;font-size:12px;font-weight:600;border:1px solid var(--primary-color,#4f46e5);border-radius:4px;cursor:pointer;"
              +(active?"background:var(--primary-color,#4f46e5);color:#fff;":"background:transparent;color:var(--primary-color,#4f46e5);"),
        onclick:function(){s.queueType=val;m.redraw();}
      },label);
    };

    // ---- path status badge --------------------------------------------------
    var pathBadge=!s.basePathLoaded
      ? m("span",{style:"font-size:11px;color:var(--muted-color);margin-left:8px;"},"loading\u2026")
      : s.basePath
        ? m("span",{style:"font-size:11px;color:var(--success-color,#10b981);margin-left:8px;"},"\u2713 path detected")
        : m("span",{style:"font-size:11px;color:#f59e0b;margin-left:8px;"},"\u26a0\ufe0f path unavailable \u2014 replace /path/to/flarum manually");

    return m("div",null,
      // ---- Queue Settings --------------------------------------------------
      m("div",{className:"ExtensionPage-settings"},
        m("div",{style:"max-width:660px;margin:0 auto;"},
          sh("Queue Settings"),
          m("div",{className:"Form-group",style:"margin-bottom:20px;"},
            m("label",{className:"label",style:"font-weight:600;display:block;margin-bottom:4px;"},"Queue Name"),
            m("p",{className:"helpText",style:"margin-bottom:6px;"},"The named queue digest jobs are pushed onto. Default: ",code("digest"),". If using the database queue, your ",code("queue:work")," cron must include this name. If using Horizon, this queue must be listed in your ",code("extend.php")," Horizon environment configuration."),
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
          sh("Cron Setup"),
          notice("\u26a0\ufe0f","These lines must be added to your server's crontab",
            ["They cannot be set from this panel. SSH into your server and run ",code("sudo crontab -u www-data -e")," to open the crontab editor for your web server user, then paste the lines shown for your queue backend below."],
            "#f59e0b"
          ),
          // Queue type toggle
          m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:20px;"},
            m("span",{style:"font-size:12px;font-weight:600;color:var(--muted-color);margin-right:4px;"},"Queue backend:"),
            toggleBtn("Sync (default)","sync"),
            toggleBtn("Database Queue","database"),
            toggleBtn("Redis / Valkey","redis"),
            pathBadge
          ),
          // ---- SYNC MODE --------------------------------------------------
          s.queueType==="sync"?m("div",null,
            notice("\u2705","No worker setup required for Sync",
              m("div",null,
                m("p",{style:"margin:0 0 8px;"},"Flarum's default ",code("sync")," driver processes jobs inline during the web request. No queue worker, no Supervisor, and no queue backend is needed. The scheduler cron line below is the only requirement."),
                m("p",{style:"margin:0;"},"When the scheduler fires at your configured send time, ",code("digest:send")," runs, builds each email, and sends it directly in the same process.")
              ),
              "#10b981"
            ),
            cronBlock("Flarum Scheduler \u2014 the only cron line you need",lineScheduler),
            notice("\u26a0\ufe0f","Sync has limits \u2014 know when to upgrade",
              m("div",null,
                m("ul",{style:"margin:0 0 10px;padding-left:18px;"},
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"Under ~50 subscribers")," \u2014 sync is fine, most servers handle it without issue"),
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"50\u2013200 subscribers")," \u2014 slow page responses, occasional timeouts"),
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"200+ subscribers")," \u2014 regular timeouts, memory exhaustion on typical VPS hosting"),
                  m("li",{style:"margin-bottom:4px;line-height:1.5;"},m("strong",null,"500+ subscribers")," \u2014 effectively broken; posts will fail or appear to hang")
                ),
                m("p",{style:"margin:0 0 6px;"},"When you hit those limits, switch to Flarum\u2019s built-in database queue driver \u2014 no extension install required. Add this to your ",code("config.php"),":"),
                m("pre",{style:"background:var(--body-bg);border:1px solid var(--control-bg);border-radius:6px;padding:10px 14px;font-family:monospace;font-size:12px;color:var(--text-color);margin:0;line-height:1.7;"},
                  "'queue' => [\n    'driver' => 'database',\n],"
                ),
                m("p",{style:"margin:8px 0 0;"},"Then switch to the ",m("strong",null,"Database Queue")," tab above for the correct cron setup.")
              ),
              "#f59e0b"
            )
          ):null,
          // ---- DATABASE MODE -----------------------------------------------
          s.queueType==="database"?m("div",null,
            notice("\u2139\ufe0f","Enable the database queue driver first",
              m("div",null,
                m("p",{style:"margin:0 0 6px;"},"Flarum 2.x includes a database queue driver built into core \u2014 no extension install required. Add this to your ",code("config.php")," in your Flarum root directory:"),
                m("pre",{style:"background:var(--body-bg);border:1px solid var(--control-bg);border-radius:6px;padding:10px 14px;font-family:monospace;font-size:12px;color:var(--text-color);margin:0;line-height:1.7;"},
                  "'queue' => [\n    'driver' => 'database',\n],"
                ),
                m("p",{style:"margin:8px 0 0;"},"Jobs are stored in your database and processed by a queue worker running via cron. Your forum stays responsive during sends \u2014 the web request dispatches jobs instantly and the worker drains them in the background.")
              ),
              "#3b82f6"
            ),
            cronBlock("1. Flarum Scheduler \u2014 required",lineScheduler),
            cronBlock("2. Queue Worker \u2014 required",lineWorker),
            m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
              code("--queue="+qn+",default")," processes digest jobs first, then other Flarum notifications. ",
              code("--max-time=55")," stops the worker cleanly before the next cron fires. ",
              code("--tries="+tries)," and ",code("--backoff=30")," match your retry settings above."
            ),
            notice("\uD83D\uDD04","Retries and backoff",
              m("div",null,
                m("p",{style:"margin:0 0 8px;"},"If a job fails \u2014 for example if your mail server is temporarily unavailable \u2014 it is automatically retried up to the number of times configured in Queue Settings above. Retries use exponential backoff: 30 seconds, then 60 seconds, then 120 seconds between attempts."),
                m("p",{style:"margin:0;"},"Permanently failed jobs land in your ",code("failed_jobs")," table. You can inspect and retry them with ",code("php flarum queue:retry all"),".")
              ),
              "#f59e0b"
            ),
            notice("\uD83E\uDE9F","Send window",
              m("div",null,
                m("p",{style:"margin:0 0 8px;"},"Instead of sending all emails at a single hour, you can set a send window \u2014 for example 1\u00a0a.m. to 5\u00a0a.m. \u2014 in the Settings tab. The scheduler dispatches one chunk of subscribers per minute throughout the window, spreading database load steadily over time instead of hitting everything at once."),
                m("p",{style:"margin:0;"},"The extension tracks its own progress and stops automatically once all subscribers have been processed. No extra cron entries are needed.")
              ),
              "#6366f1"
            ),
            cronBlock("3. Optional \u2014 Multiple Parallel Workers (larger forums)",lineWorkers3),
            m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
              "Add one cron line per additional worker. Each worker runs independently and pulls jobs off the shared queue. Start with one or two workers and add more only if your queue depth grows faster than workers drain it."
            ),
            cronBlock("4. Optional \u2014 Two-Phase Pre-Population (50,000+ subscribers only)",lineEnqueue),
            m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
              "Pre-populates the queue with jobs before the send window opens so workers have no construction overhead when they start. Set this to fire 10 minutes before your configured send window start. For example, if your window starts at 2\u00a0a.m., set this to run at 1:50\u00a0a.m. The first number is the minute (50\u00a0=\u00a0:50) and the second is the hour in 24-hour format (1\u00a0=\u00a01\u00a0a.m.). Adjust both to match your setup."
            ),
            notice("\uD83D\uDCA1","When to consider upgrading to Redis\u00a0/\u00a0Valkey",
              m("div",null,
                m("p",{style:"margin:0;"},"The database queue is reliable and sufficient for most forums. Consider upgrading to Redis or Valkey with Horizon when you have 50,000+ subscribers and need higher throughput or real-time queue monitoring. Switch to the ",m("strong",null,"Redis / Valkey")," tab for setup instructions.")
              ),
              "#6366f1"
            )
          ):null,
          // ---- REDIS / VALKEY MODE -----------------------------------------
          s.queueType==="redis"?m("div",null,
            notice("\u2139\ufe0f","Redis\u00a0/\u00a0Valkey must already be installed and running on your server",
              m("div",null,
                m("p",{style:"margin:0;"},"This extension does not cover Redis or Valkey installation. Once your Redis or Valkey server is running and ",code("fof/redis")," is installed and configured, choose your worker approach below.")
              ),
              "#3b82f6"
            ),
            m("div",{style:"display:flex;align-items:center;gap:8px;margin-bottom:20px;"},
              m("span",{style:"font-size:12px;font-weight:600;color:var(--muted-color);margin-right:4px;"},"Worker approach:"),
              (function(){
                var subActive=s.redisSubMode==="cron";
                return m("button",{
                  style:"padding:6px 18px;font-size:12px;font-weight:600;border:1px solid var(--primary-color,#4f46e5);border-radius:4px;cursor:pointer;"+(subActive?"background:var(--primary-color,#4f46e5);color:#fff;":"background:transparent;color:var(--primary-color,#4f46e5);"),
                  onclick:function(){s.redisSubMode="cron";m.redraw();}
                },"Cron-based");
              })(),
              (function(){
                var subActive=s.redisSubMode==="horizon";
                return m("button",{
                  style:"padding:6px 18px;font-size:12px;font-weight:600;border:1px solid var(--primary-color,#4f46e5);border-radius:4px;cursor:pointer;"+(subActive?"background:var(--primary-color,#4f46e5);color:#fff;":"background:transparent;color:var(--primary-color,#4f46e5);"),
                  onclick:function(){s.redisSubMode="horizon";m.redraw();}
                },"Horizon + Supervisor (recommended)");
              })()
            ),
            // ---- REDIS CRON SUB-MODE ----------------------------------------
            s.redisSubMode==="cron"?m("div",null,
              notice("\u2699\ufe0f","How this works",
                m("div",null,
                  m("p",{style:"margin:0;"},"The scheduler fires every minute via cron. When your configured send time arrives, ",code("digest:send")," dispatches jobs to Redis. A second cron worker runs every minute, connects to Redis, and drains jobs for up to 55 seconds before exiting cleanly. This approach requires no persistent processes or Supervisor.")
                ),
                "#3b82f6"
              ),
              cronBlock("1. Flarum Scheduler \u2014 required",lineScheduler),
              cronBlock("2. Queue Worker \u2014 required",lineWorker),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
                code("--queue="+qn+",default")," processes digest jobs first, then other Flarum notifications. ",
                code("--max-time=55")," stops the worker cleanly before the next cron fires. The worker connects to Redis via ",code("BLPOP")," and drains jobs for the full 55 seconds before exiting."
              ),
              notice("\uD83D\uDD04","Retries and backoff",
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},"If a job fails, it is automatically retried up to the number of times configured in Queue Settings above, using exponential backoff: 30 seconds, then 60 seconds, then 120 seconds between attempts."),
                  m("p",{style:"margin:0;"},"Permanently failed jobs land in your ",code("failed_jobs")," table. Inspect and retry them with ",code("php flarum queue:retry all"),".")
                ),
                "#f59e0b"
              ),
              notice("\uD83E\uDE9F","Send window",
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},"Set a send window in the Settings tab to spread sending across several hours. The scheduler dispatches one chunk per minute throughout the window, keeping server load low and steady."),
                  m("p",{style:"margin:0;"},"The extension tracks progress automatically and stops once all subscribers are processed.")
                ),
                "#6366f1"
              ),
              cronBlock("3. Optional \u2014 Multiple Parallel Workers (larger forums)",lineWorkers3),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
                "Add one cron line per additional worker. Each connects to Redis independently and pulls jobs from the shared queue. Start with one or two and add more only if needed."
              ),
              cronBlock("4. Optional \u2014 Two-Phase Pre-Population (50,000+ subscribers only)",lineEnqueue),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},
                "Pre-populates the queue with jobs before the send window opens so workers have no construction overhead when they start. Set this to fire 10 minutes before your configured send window start. For example, if your window starts at 2\u00a0a.m., set this to run at 1:50\u00a0a.m. The first number is the minute (50\u00a0=\u00a0:50) and the second is the hour in 24-hour format (1\u00a0=\u00a01\u00a0a.m.). Adjust both to match your setup."
              ),
              notice("\uD83D\uDCA1","Consider upgrading to Horizon\u00a0+\u00a0Supervisor",
                m("div",null,
                  m("p",{style:"margin:0;"},"For high-traffic forums, Horizon gives you persistent workers, real-time queue monitoring, automatic scaling, and a built-in dashboard. Switch to the ",m("strong",null,"Horizon + Supervisor")," tab above for full setup instructions.")
                ),
                "#6366f1"
              )
            ):null,
            // ---- REDIS HORIZON SUB-MODE -------------------------------------
            s.redisSubMode==="horizon"?m("div",null,
              notice("\u2705","Horizon is the recommended approach for Redis\u00a0/\u00a0Valkey",
                m("div",null,
                  m("p",{style:"margin:0;"},"Horizon runs as a persistent background process managed by Supervisor. It watches your queues continuously, processes jobs as they arrive, scales workers automatically, and provides a real-time dashboard at ",code("/admin/horizon"),". Unlike cron-based workers, Horizon never misses a job and never needs to be manually restarted.")
                ),
                "#10b981"
              ),
              sh("Step 1 \u2014 Install fof/horizon"),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},"Run this command from your Flarum root directory:"),
              cronBlock("",("composer require fof/horizon")),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},"Then enable ",code("FoF Horizon")," in your Flarum admin panel under Extensions."),
              sh("Step 2 \u2014 Configure extend.php"),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},"Add the following to your Flarum root ",code("extend.php"),". The ",code("digest")," queue must be listed here so Horizon processes digest jobs. The ",code("default")," queue handles all other Flarum notifications."),
              cronBlock("extend.php",("<?php\n\nuse FoF\\Redis\\Extend\\Redis;\nuse FoF\\Horizon\\Extend\\Horizon;\n\nreturn [\n    new Redis([\n        'host'     => '127.0.0.1',\n        'password' => null,\n        'port'     => 6379,\n        'database' => 0,\n    ]),\n\n    (new Horizon)->environment([\n        'supervisor-1' => [\n            'connection' => 'redis',\n            'queue'      => ['"+qn+"', 'default'],\n            'balance'    => 'auto',\n            'processes'  => 4,\n            'tries'      => "+tries+",\n            'memory'     => 128,\n        ],\n    ]),\n];")),
              sh("Step 3 \u2014 Configure Supervisor"),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},"Create the Horizon Supervisor config file at ",code("/etc/supervisor/conf.d/horizon.conf"),":"),
              cronBlock("/etc/supervisor/conf.d/horizon.conf",horizonConf),
              m("p",{style:"margin:-8px 0 8px;font-size:12px;color:var(--muted-color);"},"Adjust ",code("user")," to match your web server user (",code("www-data"),", ",code("nginx"),", or ",code("apache")," depending on your setup)."),
              m("p",{style:"margin:0 0 8px;font-size:13px;color:var(--muted-color);line-height:1.6;"},"Then load and start Horizon:"),
              cronBlock("",("sudo supervisorctl reread\nsudo supervisorctl update\nsudo supervisorctl start horizon\nsudo supervisorctl status")),
              m("p",{style:"margin:-8px 0 16px;font-size:12px;color:var(--muted-color);"},"You should see ",code("horizon")," with status ",code("RUNNING"),"."),
              sh("Step 4 \u2014 Cron Setup"),
              notice("\u26a0\ufe0f","One cron line only \u2014 do not add a queue:work line",
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},"When using Horizon + Supervisor, Horizon is your persistent queue worker. It runs continuously and listens to the ",code(qn)," and ",code("default")," queues 24\u00a07. Adding a ",code("queue:work")," cron line on top would create competing workers and cause unpredictable behaviour."),
                  m("p",{style:"margin:0;"},"The scheduler cron below is the only cron line you need.")
                ),
                "#f59e0b"
              ),
              cronBlock("Flarum Scheduler \u2014 the only cron line you need",lineScheduler),
              sh("Step 5 \u2014 Verify"),
              m("p",{style:"margin:0 0 16px;font-size:13px;color:var(--muted-color);line-height:1.6;"},"Visit your Horizon dashboard at ",code("/admin/horizon"),". You should see Horizon\u2019s status shown as ",m("strong",null,"Running")," and ",code("supervisor-1")," listed at the bottom of the dashboard showing the ",code(qn)," and ",code("default")," queues."),
              notice("\uD83E\uDE9F","Send window",
                m("div",null,
                  m("p",{style:"margin:0 0 8px;"},"Set a send window in the Settings tab to spread sending across several hours. The scheduler dispatches one chunk per minute throughout the window, keeping server load low and steady. Horizon workers drain each chunk in parallel as it arrives."),
                  m("p",{style:"margin:0;"},"The extension tracks progress automatically and stops once all subscribers are processed.")
                ),
                "#6366f1"
              ),
              cronBlock("Optional \u2014 Two-Phase Pre-Population (50,000+ subscribers only)",lineEnqueue),
              m("p",{style:"margin:-8px 0 0;font-size:12px;color:var(--muted-color);"},
                "Pre-populates the queue with jobs before the send window opens so Horizon workers have no construction overhead when they start. Set this to fire 10 minutes before your configured send window start. For example, if your window starts at 2\u00a0a.m., set this to run at 1:50\u00a0a.m. The first number is the minute (50\u00a0=\u00a0:50) and the second is the hour in 24-hour format (1\u00a0=\u00a01\u00a0a.m.). Adjust both to match your setup."
              )
            ):null
          ):null
        )
      ),
      // ---- Recommended Settings by Forum Size ------------------------------
      m("div",{className:"ExtensionPage-settings"},
        m("div",{style:"max-width:660px;margin:0 auto;"},
          sh("Recommended Settings by Forum Size"),
          m("p",{style:"margin:0 0 16px;font-size:13px;color:var(--muted-color);line-height:1.6;"},
            "These are starting-point recommendations. Actual performance depends on your server hardware, mail provider response times, and how many users have opted in to digests. Always monitor your queue depth and adjust accordingly."
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
                  var bg=i%2===0?"var(--body-bg)":"var(--control-bg)";
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
              "\uD83D\uDCA1 For 50,000+ member forums, consider switching from the built-in database queue driver to Redis or Valkey with Horizon and Supervisor for significantly higher throughput and real-time monitoring. Switch to the Redis\u00a0/\u00a0Valkey tab in the Cron Setup section above for full setup instructions. Send history is retained automatically: 30 daily entries, 52 weekly, 24 monthly."
            )
          )
        )
      )
    );
  }
};


var DigestAdminPage={
  oninit:function(vnode){vnode.state.tab="settings";},
  view:function(vnode){
    var s=vnode.state;
    var tabStyle=function(active){
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

app().initializers.add("ernestdefoe-digest-mail",function(){
  var style=document.createElement("style");
  style.textContent=".Select-input.FormControl{line-height:1.4 !important;padding-bottom:8px !important;height:auto !important;}";
  document.head.appendChild(style);
  app().registry.for("ernestdefoe-digest-mail").registerSetting(function(){return m(DigestAdminPage);},100);
});

})(),module.exports=o})();
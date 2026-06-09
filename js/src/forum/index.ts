import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Modal from 'flarum/common/components/Modal';

var DigestOptInModal=class extends(Modal){
  className(){return "DigestOptInModal Modal--small";}
  title(){return app.translator.trans("ernestdefoe-digest-mail.forum.onboarding.modal_title");}

  // Override hide() so closing via the X button also clears the flag.
  // Without this, the X calls hide() directly and the flag stays set,
  // causing the modal to reappear on every page load.
  hide(){
    var user=app.session.user;
    if(user){
      var prefs=user.preferences()||{};
      if(prefs.digest_onboarding_pending){
        prefs.digest_onboarding_pending=null;
        user.save({preferences:prefs});
      }
    }
    super.hide();
  }

  content(){
    var self=this;
    var user=app.session.user;
    var allowed=app.forum.attribute("digestAllowedFrequencies")||{};
    var freqLabels={"daily":"ernestdefoe-digest-mail.forum.settings.frequency_daily","weekly":"ernestdefoe-digest-mail.forum.settings.frequency_weekly","monthly":"ernestdefoe-digest-mail.forum.settings.frequency_monthly"};

    function choose(frequency){
      var prefs=user.preferences()||{};
      prefs.digest_onboarding_pending=null;
      user.save({digestFrequency:frequency,preferences:prefs}).then(function(){self.hide();}).catch(function(){self.hide();});
    }

    function dismiss(){
      var prefs=user.preferences()||{};
      prefs.digest_onboarding_pending=null;
      user.save({digestFrequency:null,preferences:prefs}).then(function(){self.hide();}).catch(function(){self.hide();});
    }

    var buttons=["daily","weekly","monthly"].filter(function(f){return !!allowed[f];}).map(function(f){
      return m("button",{
        className:"Button Button--primary",
        onclick:function(e){e.preventDefault();choose(f);}
      },app.translator.trans(freqLabels[f]));
    });

    return m("div",{className:"Modal-body",style:"padding:20px;"},
      m("p",{style:"margin-bottom:16px;"},app.translator.trans("ernestdefoe-digest-mail.forum.onboarding.modal_body")),
      m("div",{style:"display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;justify-content:center;"},buttons),
      m("button",{
        className:"Button Button--text",
        style:"display:block;width:100%;margin-top:8px;",
        onclick:function(e){e.preventDefault();dismiss();}
      },app.translator.trans("ernestdefoe-digest-mail.forum.onboarding.modal_dismiss"))
    );
  }
};

var DigestFrequencySetting={
  oninit:function(vnode){
    this.user=vnode.attrs.user;
    this.saving=false;
    this.saved=false;
    this.error=null;
  },
  view:function(){
    var self=this;
    var user=this.user;
    var value=user.attribute("digestFrequency")||"off";
    var allowed=app.forum.attribute("digestAllowedFrequencies")||{daily:false,weekly:true,monthly:true};
    var effectiveValue=(value!=="off"&&!allowed[value])?"off":value;
    var options=[
      m("option",{value:"off"},app.translator.trans("ernestdefoe-digest-mail.forum.settings.frequency_off"))
    ];
    if(allowed.daily)  options.push(m("option",{value:"daily"},  app.translator.trans("ernestdefoe-digest-mail.forum.settings.frequency_daily")));
    if(allowed.weekly) options.push(m("option",{value:"weekly"}, app.translator.trans("ernestdefoe-digest-mail.forum.settings.frequency_weekly")));
    if(allowed.monthly)options.push(m("option",{value:"monthly"},app.translator.trans("ernestdefoe-digest-mail.forum.settings.frequency_monthly")));
    return m("div",{class:"Form-group"},
      m("label",{class:"label",for:"ernestdefoe-digest-mail-frequency"},
        app.translator.trans("ernestdefoe-digest-mail.forum.settings.digest_label")
      ),
      m("div",{class:"helpText"},
        app.translator.trans("ernestdefoe-digest-mail.forum.settings.digest_help")
      ),
      m("div",{style:"display:flex;align-items:center;gap:10px;margin-top:8px;"},
        m("select",{
          id:"ernestdefoe-digest-mail-frequency",
          class:"FormControl",
          style:"padding-top:6px;padding-bottom:8px;height:auto;line-height:1.5;",
          disabled:this.saving,
          value:effectiveValue,
          onchange:function(e){self.save(user,e.target.value);}
        },options),
        this.saving?m("span",{class:"LoadingIndicator","aria-hidden":"true"}):null,
        (this.saved&&!this.saving)
          ?m("span",{style:"color:var(--control-success-color,#3d8b3d);font-size:13px;"},
              "\u2713 "+app.translator.trans("ernestdefoe-digest-mail.forum.settings.saved"))
          :null
      ),
      this.error
        ?m("div",{class:"Alert Alert--error",style:"margin-top:8px;padding:8px 12px;font-size:13px;"},this.error)
        :null
    );
  },
  save:function(user,value){
    var self=this;
    var frequency=value==="off"?null:value;
    this.saving=true;this.saved=false;this.error=null;m.redraw();
    user.save({digestFrequency:frequency})
      .then(function(){
        self.saving=false;self.saved=true;m.redraw();
        setTimeout(function(){self.saved=false;m.redraw();},3000);
      })
      .catch(function(e){
        self.saving=false;
        self.error=(e&&e.message)||app.translator.trans("ernestdefoe-digest-mail.forum.settings.save_error");
        m.redraw();
      });
  }
};

app.initializers.add("ernestdefoe-digest-mail",function(){
  extend("flarum/forum/components/SettingsPage","notificationsItems",function(items){
    var user=this.user;
    if(!user||!app.session.user||user.id()!==app.session.user.id())return;
    items.add("digestFrequency",m(DigestFrequencySetting,{user:user}),50);
  });

  // -------------------------------------------------------------------------
  // Opt-in modal boot hook
  //
  // On every page load, if the current user has the digest_onboarding_pending
  // preference set to true, open the DigestOptInModal. The modal clears the
  // flag on any interaction so it never shows more than once.
  // -------------------------------------------------------------------------
  setTimeout(function(){
    var user=app.session.user;
    if(!user)return;
    var prefs=user.preferences();
    if(!prefs||prefs.digest_onboarding_pending!==true)return;
    app.modal.show(DigestOptInModal);
  },0);
});

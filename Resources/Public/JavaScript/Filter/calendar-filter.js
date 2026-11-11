import{html as w,LitElement as Z,nothing as k}from"lit";var S=[{saturation:76,lightness:44},{saturation:88,lightness:50},{saturation:72,lightness:38},{saturation:92,lightness:56},{saturation:78,lightness:62}],N=n=>{let i=2166136261;for(let e of n)i^=e.charCodeAt(0),i=Math.imul(i,16777619);return i^=i>>>16,i=Math.imul(i,2246822507),i^=i>>>13,i=Math.imul(i,3266489909),(i^i>>>16)>>>0},E=(n,i,e={})=>{let t=e[n]?.[String(i)];if(t)return t;let r=N(`${n}:${i}`)%200,s=S[Math.floor(r/(200/S.length))];return`hsl(${Math.round(r*137.508%360)} ${s.saturation}% ${s.lightness}%)`};import{html as A,LitElement as U}from"lit";var g=class extends U{createRenderRoot(){return this}render(){let i=window.top,t=(i.TYPO3?.settings?.ximaCalendar?.locale??document.documentElement.lang??navigator.language)?.startsWith("de")?"Suchbegriff eingeben":i.TYPO3?.lang?.["filter.search"]??"Enter search term";return A`
      <div class="tree-toolbar">
        <div class="tree-toolbar__menu">
          <div class="tree-toolbar__search" hidden>
            <label
              for="xima-calendar-filter-search"
              class="visually-hidden"
            >
              ${t}
            </label>

            <input
              id="xima-calendar-filter-search"
              type="search"
              class="form-control form-control-sm search-input"
              placeholder=${t}
            />
          </div>
        </div>
      </div>
    `}};customElements.define("xima-calendar-filter-toolbar",g);import{html as h,LitElement as j,nothing as v}from"lit";function _(n,i,e){return e?[...new Set([...n,i])]:n.filter(t=>t!==i)}function Y(n,i){let e=[],t=[i];for(;t.length>0;){let a=t.pop();if(a!==void 0)for(let r of n)r.parentUid===a&&(e.push(r.value),t.push(r.value))}return e}function L(n,i,e,t){let a=Y(i,e);return t?[...new Set([...n,...a])]:n.filter(r=>!a.includes(r))}function M(n,i,e){return{...n,[i]:e}}var b=class extends j{constructor(){super(...arguments);this.identifier="";this.treeTitle="";this.actionLabels=null;this.items=[];this.activeItems=[];this.expandedItems={};this.openActionValue=null}static{this.properties={identifier:{attribute:!1},treeTitle:{attribute:!1},actionLabels:{attribute:!1},items:{attribute:!1},activeItems:{attribute:!1},expandedItems:{attribute:!1}}}createRenderRoot(){return this}render(){let e=this.getLanguageLabel("filter.moreActions","More actions"),t=this.getLanguageLabel("filter.selectAllChildren","Select all nested ###name###"),a=this.getLanguageLabel("filter.deselectAllChildren","Deselect all nested ###name###");return h`
      <div class="xima-calendar-filter__tree">
        ${this.renderTreeNodes(null,e,t,a)}
      </div>
    `}renderTreeNodes(e,t,a,r){return this.items.filter(l=>l.parentUid===e).map(l=>{let c=this.items.some(d=>d.parentUid===l.value),o=this.expandedItems[String(l.value)]??!1,R=this.activeItems.includes(l.value);return h`
        <div class="xima-calendar-filter__tree-item">
          <div class="xima-calendar-filter__tree-node">
            <button
              type="button"
              class=${c?"xima-calendar-filter__tree-toggle":"xima-calendar-filter__tree-toggle xima-calendar-filter__tree-toggle--empty"}
              aria-expanded=${o}
              aria-hidden=${!c}
              tabindex=${c?"0":"-1"}
              @click=${()=>this.handleExpansionChange(l.value,!o)}
            >
              <typo3-backend-icon
                identifier=${o?"actions-chevron-down":"actions-chevron-end"}
                size="small"
              ></typo3-backend-icon>
            </button>

            <label class="form-check">
              <input
                class="xima-calendar-filter__tree-checkbox"
                type="checkbox"
                .checked=${R}
                style=${l.color?`--xima-tree-item-color: ${l.color}`:""}
                @change=${d=>this.handleSelectionChange(l.value,d.target.checked)}
              />

              <span class="form-check-label">
        ${l.label}
      </span>
            </label>

            ${c?h`
                <div class="xima-calendar-filter__tree-actions">
                  <button
                    type="button"
                    class="xima-calendar-filter__tree-actions-toggle"
                    aria-label=${t}
                    aria-haspopup="menu"
                    aria-expanded=${this.openActionValue===l.value}
                    title=${t}
                    @click=${d=>{d.stopPropagation(),this.toggleActionMenu(l.value)}}
                  >
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                  </button>

                  ${this.openActionValue===l.value?h`
                      <div
                        class="xima-calendar-filter__tree-actions-menu"
                        role="menu"
                      >
                        <button
                          type="button"
                          role="menuitem"
                          @click=${d=>{d.stopPropagation(),this.handleDescendantSelection(l.value,!0)}}
                        >${a}</button>
                        <button
                          type="button"
                          role="menuitem"
                          @click=${d=>{d.stopPropagation(),this.handleDescendantSelection(l.value,!1)}}
                        >${r}</button>
                      </div>
                    `:v}
                </div>
              `:v}
          </div>

          ${c&&o?h`
              <div class="xima-calendar-filter__tree-children">
                ${this.renderTreeNodes(l.value,t,a,r)}
              </div>
            `:v}
        </div>
      `})}getLanguageLabel(e,t){let a=e.replace("filter.","");return(this.actionLabels?.[a]??t).replace("###name###",this.treeTitle)}handleSelectionChange(e,t){this.activeItems=_(this.activeItems,e,t),this.dispatchEvent(new CustomEvent("tree-state-change",{detail:{identifier:this.identifier,activeItems:this.activeItems,expandedItems:this.expandedItems},bubbles:!0}))}toggleActionMenu(e){this.openActionValue=this.openActionValue===e?null:e,this.requestUpdate()}handleDescendantSelection(e,t){this.activeItems=L(this.activeItems,this.items,e,t),this.openActionValue=null,this.dispatchEvent(new CustomEvent("tree-state-change",{detail:{identifier:this.identifier,activeItems:this.activeItems,expandedItems:this.expandedItems},bubbles:!0}))}handleExpansionChange(e,t){this.expandedItems=M(this.expandedItems,e,t),this.dispatchEvent(new CustomEvent("tree-state-change",{detail:{identifier:this.identifier,activeItems:this.activeItems,expandedItems:this.expandedItems},bubbles:!0}))}};customElements.define("xima-calendar-filter-tree",b);import{html as u,LitElement as q,nothing as $}from"lit";var C=class extends q{constructor(){super(...arguments);this.identifier="";this.items=[];this.activeItems=[]}static{this.properties={identifier:{attribute:!1},items:{attribute:!1},activeItems:{attribute:!1}}}createRenderRoot(){return this}render(){return u`
      <div class="xima-calendar-filter__checkbox-list ${this.identifier==="statuses"?"xima-calendar-filter__checkbox-list--statuses":""} ${this.hasAppearance()?"xima-calendar-filter__checkbox-list--with-appearance":""}">
          ${this.items.map(e=>u`
          <label class="form-check">
            <input
              class="form-check-input"
              type="checkbox"
              value=${e.value}
              .checked=${this.activeItems.includes(e.value)}
              @change=${t=>this.handleChange(e.value,t.target.checked)}
            />
            <span class="form-check-label xima-calendar-filter__checkbox-label">
              ${e.icon?u`
                    <typo3-backend-icon
                      identifier=${e.icon}
                      size="small"
                    ></typo3-backend-icon>
                  `:$}
              ${e.label}
            </span>
            ${e.marker?u`<span class="xima-calendar-filter__checkbox-appearance xima-calendar-filter__checkbox-appearance--marker">
                  ${e.marker}
                </span>`:e.appearance?u`<span
                  class="xima-calendar-filter__checkbox-appearance ${this.getItemClass(e.appearance)}"
                  aria-hidden="true"
                ></span>`:this.identifier==="statuses"?u`<span
                    class="xima-calendar-filter__checkbox-appearance xima-calendar-filter__checkbox-appearance--empty"
                    aria-hidden="true"
                  ></span>`:$}
          </label>
        `)}
      </div>
    `}handleChange(e,t){this.dispatchEvent(new CustomEvent("checkbox-list-change",{detail:{identifier:this.identifier,value:e,selected:t},bubbles:!0,composed:!0}))}getItemClass(e){return e?`xima-calendar-filter__checkbox-appearance--${e}`:""}hasAppearance(){return this.items.some(e=>!!(e.appearance||e.marker))}};customElements.define("xima-calendar-filter-checkbox-list",C);import{html as m,LitElement as H,nothing as B}from"lit";function x(n){let i=n.getFullYear(),e=String(n.getMonth()+1).padStart(2,"0"),t=String(n.getDate()).padStart(2,"0");return`${i}-${e}-${t}`}var p={mainDateChange:"xima-calendar-main-date-change",navigate:"xima-calendar-mini-calendar-navigate",dateChange:"xima-calendar-mini-calendar-date-change",ready:"xima-calendar-mini-calendar-ready"};var F=3,G=5,y=class extends H{constructor(){super(...arguments);this.displayedMonth=this.startOfMonth(new Date);this.mainViewType="";this.mainViewDate=this.startOfDay(new Date);this.pendingClickedDate=null;this.pendingNavigationMonth=null;this.handleMainCalendarDateChange=e=>{let t=e.detail,a=t?.date;if(!a)return;let r=new Date(a);if(Number.isNaN(r.getTime()))return;if(this.pendingClickedDate!==null&&(t?.viewType!=="timeGridWeek"||!this.isSameMiniWeek(r,this.pendingClickedDate))){this.mainViewDate=this.startOfDay(r),this.mainViewType=t?.viewType??"",this.requestUpdate();return}if(this.pendingNavigationMonth!==null&&(t?.viewType==="timeGridWeek"?!this.isSameMiniWeek(r,this.pendingNavigationMonth):!this.isSameMonth(r,this.pendingNavigationMonth))){this.mainViewDate=this.startOfDay(r),this.mainViewType=t?.viewType??"",this.requestUpdate();return}let s=this.pendingClickedDate??this.pendingNavigationMonth??r;this.pendingClickedDate=null,this.pendingNavigationMonth=null,this.displayedMonth=this.startOfMonth(s),this.mainViewDate=this.startOfDay(s),this.mainViewType=t?.viewType??"",this.requestUpdate()}}get topWindow(){return window.top??window}connectedCallback(){super.connectedCallback(),this.topWindow.addEventListener(p.mainDateChange,this.handleMainCalendarDateChange),this.topWindow.dispatchEvent(new CustomEvent(p.ready))}disconnectedCallback(){this.topWindow.removeEventListener(p.mainDateChange,this.handleMainCalendarDateChange),super.disconnectedCallback()}createRenderRoot(){return this}render(){let e=this.getLocale(),t=new Intl.DateTimeFormat(e,{month:"long"}).format(this.displayedMonth);return m`
      <div class="xima-calendar-filter__mini-calendar-header">
        <button
          type="button"
          class="xima-calendar-filter__mini-calendar-navigation"
          aria-label=${e.startsWith("de")?"Vorheriger Monat":"Previous month"}
          @click=${()=>this.changeMonth(-1)}
        >‹</button>
        <div class="xima-calendar-filter__mini-calendar-title">
          <h3>${t}</h3>
          <span class="xima-calendar-filter__mini-calendar-year-select">
            <select
              class="xima-calendar-filter__mini-calendar-year"
              name="calendar-year"
              aria-label=${e.startsWith("de")?"Jahr ausw\xE4hlen":"Select year"}
              value=${String(this.displayedMonth.getFullYear())}
              @change=${a=>this.changeYear(a)}
            >
              ${this.getYearOptions().map(a=>m`
                <option
                  value=${a}
                  ?selected=${a===this.displayedMonth.getFullYear()}
                >${a}</option>
              `)}
            </select>
          </span>
        </div>
        <button
          type="button"
          class="xima-calendar-filter__mini-calendar-navigation"
          aria-label=${e.startsWith("de")?"N\xE4chster Monat":"Next month"}
          @click=${()=>this.changeMonth(1)}
        >›</button>
      </div>

      <div class="xima-calendar-filter__mini-calendar-weekdays" aria-hidden="true">
        ${this.getWeekdays(e).map(a=>m`<span>${a}</span>`)}
      </div>

      <div class="xima-calendar-filter__mini-calendar-grid">
        ${this.getDays().map(a=>m`
          <button
            type="button"
            class=${this.getDayClass(a)}
            aria-current=${a.isToday?"date":B}
            aria-label=${this.getDateLabel(a.date,e)}
            @click=${()=>this.selectDate(a.date)}
          >${a.day}</button>
        `)}
      </div>
    `}getLocale(){return window.top.TYPO3?.settings?.ximaCalendar?.locale??document.documentElement.lang??navigator.language}getWeekdays(e){let t=new Intl.DateTimeFormat(e,{weekday:"short"}),a=new Date(2024,0,1);return Array.from({length:7},(r,s)=>t.format(new Date(a.getFullYear(),a.getMonth(),a.getDate()+s)).charAt(0).toUpperCase())}getYearOptions(){let e=new Date().getFullYear();return Array.from({length:F+G+1},(t,a)=>e-F+a)}getDays(){let e=this.startOfMonth(this.displayedMonth),t=(e.getDay()+6)%7,a=new Date(e.getFullYear(),e.getMonth()+1,0).getDate(),r=Math.ceil((t+a)/7),s=this.startOfDay(new Date);return Array.from({length:r*7},(l,c)=>{let o=new Date(e);return o.setDate(e.getDate()+c-t),{date:o,day:o.getDate(),isCurrentMonth:o.getMonth()===this.displayedMonth.getMonth(),isToday:this.isSameDay(o,s),isInDisplayedWeek:this.mainViewType==="timeGridWeek"&&this.isSameDay(this.startOfMiniWeek(o),this.startOfMiniWeek(this.mainViewDate))}})}getDayClass(e){return["xima-calendar-filter__mini-calendar-day",e.isCurrentMonth?"":"xima-calendar-filter__mini-calendar-day--outside",e.isToday?"xima-calendar-filter__mini-calendar-day--today":"",e.isInDisplayedWeek?"xima-calendar-filter__mini-calendar-day--current-week":""].filter(Boolean).join(" ")}getDateLabel(e,t){return new Intl.DateTimeFormat(t,{dateStyle:"full"}).format(e)}changeMonth(e){let t=new Date(this.displayedMonth.getFullYear(),this.displayedMonth.getMonth()+e,1);this.navigateToMonth(t,e)}changeYear(e){let t=Number(e.target.value);if(!Number.isInteger(t))return;let a=t<this.displayedMonth.getFullYear()?-1:1;this.navigateToMonth(new Date(t,this.displayedMonth.getMonth(),1),a)}navigateToMonth(e,t){this.pendingNavigationMonth=e,this.displayedMonth=this.startOfMonth(e),this.mainViewDate=this.startOfDay(e),this.requestUpdate(),this.topWindow.dispatchEvent(new CustomEvent(p.navigate,{detail:{direction:t<0?"prev":"next",date:e.toISOString()}}))}selectDate(e){let t=this.startOfDay(e);this.pendingClickedDate=t,this.displayedMonth=this.startOfMonth(e),this.mainViewDate=t,this.mainViewType="timeGridWeek",this.dispatchEvent(new CustomEvent("mini-calendar-date-change",{bubbles:!0,composed:!0,detail:{date:x(this.startOfDay(e))}})),this.topWindow.dispatchEvent(new CustomEvent(p.dateChange,{detail:{date:x(this.startOfDay(e))}})),this.requestUpdate()}startOfMonth(e){return new Date(e.getFullYear(),e.getMonth(),1)}startOfDay(e){return new Date(e.getFullYear(),e.getMonth(),e.getDate())}startOfMiniWeek(e){let t=this.startOfDay(e),a=(t.getDay()+6)%7;return t.setDate(t.getDate()-a),t}isSameDay(e,t){return e.getFullYear()===t.getFullYear()&&e.getMonth()===t.getMonth()&&e.getDate()===t.getDate()}isSameMonth(e,t){return e.getFullYear()===t.getFullYear()&&e.getMonth()===t.getMonth()}isSameMiniWeek(e,t){return this.isSameDay(this.startOfMiniWeek(e),this.startOfMiniWeek(t))}};customElements.define("xima-calendar-filter-mini-calendar",y);import z from"@typo3/core/ajax/ajax-request.js";var f=class{constructor(i){this.url=i;this.saveQueue=Promise.resolve()}save(i){let e={sections:{...i.sections},checkboxLists:Object.fromEntries(Object.entries(i.checkboxLists).map(([t,a])=>[t,[...a]])),trees:Object.fromEntries(Object.entries(i.trees).map(([t,a])=>[t,{activeItems:[...a.activeItems],expandedItems:{...a.expandedItems}}]))};this.saveQueue=this.saveQueue.catch(()=>{}).then(async()=>{try{await(await new z(this.url).post(JSON.stringify(e),{headers:{"Content-Type":"application/json"}})).resolve()}catch(t){console.error("Calendar filter state could not be saved",t)}})}};var T="xima-calendar-tree-event-values-updated";import Q from"@typo3/core/ajax/ajax-request.js";async function V(n){return(await new Q(n).get()).resolve()}function I(n){if(!n||typeof n!="object")return!1;let i=n,e=i.filters,t=i.filterColors,a=i.state,r=i.labels;if(i.success!==!0||!e||typeof e!="object"||!t||typeof t!="object"||!a||typeof a!="object"||!r||typeof r!="object")return!1;let s=a;return!!e&&typeof e=="object"&&!!t&&typeof t=="object"&&!!s.checkboxLists&&typeof s.checkboxLists=="object"&&!!s.trees&&typeof s.trees=="object"&&!!s.sections&&typeof s.sections=="object"}function O(n,i){let{identifier:e,value:t,selected:a}=i;return{...n,checkboxLists:{...n.checkboxLists,[e]:a?[...new Set([...n.checkboxLists[e]??[],t])]:(n.checkboxLists[e]??[]).filter(r=>r!==t)}}}function W(n,i){return{...n,trees:{...n.trees,[i.identifier]:{activeItems:[...i.activeItems],expandedItems:{...i.expandedItems}}}}}function P(n){let i=Object.fromEntries(Object.entries(n.trees).map(([e,t])=>[e,[...t.activeItems]]));return Object.entries(n.checkboxLists).forEach(([e,t])=>{i[e]=[...t]}),{filters:i}}var J="xima-calendar-filter-element",D=class extends Z{constructor(){super(...arguments);this.filterConfiguration=null;this.filterColors={};this.filterState=null;this.filterLabels=null;this.filterLoadError=!1;this.filterStatePersistence=null;this.treeEventValues={};this.treeEventValuesLoaded=!1;this.handleTreeEventValuesUpdated=e=>{let t=e.detail;!t||typeof t!="object"||(this.treeEventValues=t,this.treeEventValuesLoaded=!0,this.requestUpdate())}}connectedCallback(){super.connectedCallback(),(window.top??window).addEventListener(T,this.handleTreeEventValuesUpdated);let t=window.top,a=t.TYPO3?.settings?.ajaxUrls?.xima_calendar_filter_state??t.TYPO3?.settings?.ximaCalendar?.filterStateUrl;a&&(this.filterStatePersistence=new f(a)),this.loadFilterConfiguration()}disconnectedCallback(){(window.top??window).removeEventListener(T,this.handleTreeEventValuesUpdated),super.disconnectedCallback()}createRenderRoot(){return this}render(){return w`
      <link rel="stylesheet" href="${new URL("../../Css/calendar-filter.css",import.meta.url).href}">

      <div class="xima-calendar-filter">
        <xima-calendar-filter-toolbar></xima-calendar-filter-toolbar>

        <div class="xima-calendar-filter__body">
          ${this.filterConfiguration===null?k:Object.values(this.filterConfiguration).map(e=>this.renderFilter(e))}
        </div>

        <section
          class="xima-calendar-filter__mini-calendar"
          aria-label="Mini calendar"
        >
          <xima-calendar-filter-mini-calendar></xima-calendar-filter-mini-calendar>
        </section>
      </div>
    `}renderTreeFilter(e){if(e.items.length===0)return k;let t=this.filterState?.trees[e.identifier]??{activeItems:[],expandedItems:{}},a=new Set(e.items.map(s=>s.parentUid).filter(s=>s!==null).map(s=>String(s))),r=e.items.map(s=>{let l=a.has(String(s.value)),c=this.treeEventValues[e.identifier]?.includes(String(s.value))??!1;return{...s,color:e.supportsColorCoding?l&&this.treeEventValuesLoaded&&!c?"color-mix(in srgb, var(--typo3-component-color) 45%, var(--typo3-component-bg))":E(e.identifier,s.value,this.filterColors):void 0}});return w`
      <section class="xima-calendar-filter__section">
        <details
          .open=${this.filterState?.sections[e.identifier]??!0}
          @toggle=${s=>this.handleSectionToggle(e.identifier,s)}
        >
          <summary>
            <span class="xima-calendar-filter__section-toggle" aria-hidden="true"></span>
            <h3>${e.title}</h3>
          </summary>

          <xima-calendar-filter-tree
            .identifier=${e.identifier}
            .treeTitle=${e.title}
            .actionLabels=${this.filterLabels}
            .items=${r}
            .activeItems=${t.activeItems}
            .expandedItems=${t.expandedItems}
            @tree-state-change=${this.handleTreeStateChange}
          ></xima-calendar-filter-tree>
        </details>
      </section>
    `}renderFilter(e){return e.type==="tree"?this.renderTreeFilter(e):this.renderCheckboxListFilter(e)}handleTreeStateChange(e){this.filterState!==null&&this.updateFilterState(t=>W(t,e.detail))}renderCheckboxListFilter(e){if(e.items.length===0)return k;let t=this.filterState?.checkboxLists[e.identifier]??[];return w`
      <section class="xima-calendar-filter__section">
        <details
          .open=${this.filterState?.sections[e.identifier]??!0}
          @toggle=${a=>this.handleSectionToggle(e.identifier,a)}
        >
          <summary>
            <span class="xima-calendar-filter__section-toggle" aria-hidden="true"></span>
            <h3>${e.title}</h3>
          </summary>
          <xima-calendar-filter-checkbox-list
            .identifier=${e.identifier}
            .items=${e.items}
            .activeItems=${t}
            @checkbox-list-change=${this.handleCheckboxListChange}
          ></xima-calendar-filter-checkbox-list>
        </details>
      </section>
    `}async loadFilterConfiguration(){let e=window.top,t=e.TYPO3?.settings?.ajaxUrls?.xima_calendar_filter_options??e.TYPO3?.settings?.ximaCalendar?.filterOptionsUrl;if(!t){console.error("Calendar filter URL is not available"),this.filterLoadError=!0,this.requestUpdate();return}try{let a=await V(t);if(!I(a))throw new Error("Filter configuration could not be loaded");this.filterConfiguration=a.filters,this.filterColors=a.filterColors,this.filterState=a.state,this.filterLabels=a.labels,this.publishFilterConfiguration(),this.publishFilterState()}catch(a){console.error("Calendar filter configuration could not be loaded",a),this.filterLoadError=!0}this.requestUpdate()}handleCheckboxListChange(e){this.filterState!==null&&this.updateFilterState(t=>O(t,e.detail))}handleSectionToggle(e,t){let a=t.currentTarget;!(a instanceof HTMLDetailsElement)||this.filterState===null||this.updateFilterState(r=>({...r,sections:{...r.sections,[e]:a.open}}))}publishFilterConfiguration(){if(this.filterConfiguration===null)return;let e=window.top;e.ximaCalendarFilterConfiguration=this.filterConfiguration,e.dispatchEvent(new CustomEvent("xima-calendar-filter-configuration-loaded",{bubbles:!0,composed:!0,detail:this.filterConfiguration}))}publishFilterState(){if(this.filterState===null)return;let e=P(this.filterState),t=window.top;t.ximaCalendarFilterState=e,t.dispatchEvent(new CustomEvent("xima-calendar-filter-changed",{bubbles:!0,composed:!0,detail:e}))}dispatchFilterChange(){this.publishFilterState(),this.requestUpdate(),this.filterState!==null&&this.filterStatePersistence?.save(this.filterState)}updateFilterState(e){this.filterState!==null&&(this.filterState=e(this.filterState),this.dispatchFilterChange())}};customElements.define(J,D);export{D as CalendarFilterElement,J as navigationComponentName};

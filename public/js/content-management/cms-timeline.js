/**
 * cms-timeline.js
 * Timeline interactions inside the case viewer.
 * The timeline HTML is server-rendered — this handles client interactions only.
 */
(function () {
    'use strict';

    window.CMS = window.CMS || {};

    CMS.timeline = {
        // Highlight a specific stage in the timeline
        highlightStage: function (stage) {
            document.querySelectorAll('.timeline-stage').forEach(function (el) {
                el.style.opacity = el.dataset.stage === stage ? '1' : '0.4';
            });
        },

        // Reset all stages
        resetHighlight: function () {
            document.querySelectorAll('.timeline-stage').forEach(function (el) {
                el.style.opacity = '1';
            });
        },
    };

})();

{* progress bar of a queued/running job, updated by panel.js; $job: id, status, progress, message *}
<div class="progress" role="progressbar" style="height: 1.1rem; min-width: 8rem" data-job="{$job.id}" aria-label="{'progress'|t}">
  <div class="progress-bar progress-bar-striped progress-bar-animated{if $job.status=='queued'} bg-secondary{/if}"
       style="width: {if $job.progress===null}100{else}{$job.progress}{/if}%">{if $job.progress!==null}{$job.progress}%{elseif $job.status=='queued'}{'queued'|t}{/if}</div>
</div>
<small class="text-body-secondary" data-job-message="{$job.id}">{$job.message}</small>

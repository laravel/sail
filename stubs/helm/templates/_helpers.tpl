{{/*
Expand the name of the chart.
Works with both .Values.name (standard context) and .main.name (_spec template context).
Uses .main.name if in _spec context, otherwise .Values.name, with default "website".
Falls back to standard Helm naming if neither is available.
*/}}
{{- define "sail.name" -}}
{{- $name := "" }}
{{- if and .main .main.name }}
{{- $name = .main.name | default "website" | lower }}
{{- else if .Values.name }}
{{- $name = .Values.name | default "website" | lower }}
{{- else if .Chart }}
{{- $name = default .Chart.Name .Values.nameOverride }}
{{- else }}
{{- $name = "website" }}
{{- end }}
{{- $name | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
Uses .Values.name if provided, otherwise falls back to standard Helm naming.
*/}}
{{- define "sail.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else if .Values.name }}
{{- .Values.name | lower | trunc 63 | trimSuffix "-" }}
{{- else if and .Chart .Release }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- else }}
{{- include "sail.name" . }}
{{- end }}
{{- end }}

{{/*
Detect the ExternalSecret API version available in the cluster.
Returns "external-secrets.io/v1" if available, otherwise falls back to "external-secrets.io/v1beta1".
*/}}
{{- define "sail.externalSecret.apiVersion" -}}
{{- if .Capabilities.APIVersions.Has "external-secrets.io/v1" -}}
external-secrets.io/v1
{{- else if .Capabilities.APIVersions.Has "external-secrets.io/v1beta1" -}}
external-secrets.io/v1beta1
{{- else -}}
external-secrets.io/v1beta1
{{- end -}}
{{- end }}

{{/*
Standard Kubernetes labels following best practices.
Includes selector labels plus additional metadata labels.
Note: app.kubernetes.io/instance is included here for pod identification
but NOT in selectorLabels to keep selectors immutable.
*/}}
{{- define "sail.labels" -}}
{{- if .Chart }}
helm.sh/chart: {{ include "sail.chart" . }}
{{- end }}
{{ include "sail.selectorLabels" . }}
{{- if .Release }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
{{- if .Release }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}
{{- end }}

{{/*
Selector labels used by deployments, services, etc.
NOTE: These labels must be immutable. We include app.kubernetes.io/instance
for backward compatibility with existing deployments. If you need to change
the release name, you will need to delete and recreate the Deployment.
*/}}
{{- define "sail.selectorLabels" -}}
app.kubernetes.io/name: {{ include "sail.name" . }}
{{- if .Release }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}
{{- end }}

{{/*
Chart name and version as used by the chart label.
*/}}
{{- define "sail.chart" -}}
{{- if and .Chart .Chart.Name .Chart.Version }}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- "chart-unknown" }}
{{- end }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "sail.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "sail.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}


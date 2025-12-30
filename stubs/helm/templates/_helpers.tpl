{{/*
Expand the name of the chart.
*/}}
{{- define "sail.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
*/}}
{{- define "sail.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
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

